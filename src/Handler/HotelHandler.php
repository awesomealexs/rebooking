<?php

namespace App\Handler;

use App\Enum\FileCutType;
use App\Helper\FileCutter;
use App\Helper\JsonHandler;
use App\Notify\TelegramNotifier;
use App\RatehawkApi\Configuration;
use App\RatehawkApi\RatehawkApi;
use App\Repository\HotelRepository;
use App\Repository\LocationRepository;
use App\Repository\NewHotelRepository;
use Doctrine\ORM\EntityManager;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Symfony\Component\Dotenv\Dotenv;

class HotelHandler
{

    public const BASE_DIR = __DIR__.'/../';

    public const STORAGE_DIR = self::BASE_DIR . '/Storage';

    protected const FILE_HANDLE_PATH = self::STORAGE_DIR . '/fileHandle.json';

    protected RatehawkApi $rateHawkApi;

    protected Logger $logger;

    protected JsonHandler $jsonHandler;

    protected EntityManager $entityManager;

    protected HotelRepository $hotelRepository;

    protected LocationRepository $locationRepository;

    protected FileCutter $fileCutter;

    protected TelegramNotifier $telegramNotifier;

    protected NewHotelRepository $newHotelRepository;
    public function __construct(){
        ini_set('memory_limit', '1G');
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';


        $dotenv = new Dotenv(true);
        $dotenv
            ->usePutenv()
            ->bootEnv(dirname(__DIR__ , 2). '/.env');

        $params = [
            'driver' => getenv('DB_DRIVER'),
            'user' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
            'dbname' => getenv('DB_NAME'),
            'host' => getenv('DB_HOST'),
            'port' => getenv('DB_PORT'),
        ];

        $isDevMode = true;

        $this->entityManager = EntityManager::create($params, \Doctrine\ORM\Tools\Setup::createAttributeMetadataConfiguration([__DIR__ . "/Entity"], $isDevMode));

        $logDir = static::BASE_DIR . '/Logs/ApiLog';
        $handler = new RotatingFileHandler($logDir);

        $this->logger = new Logger('', [], [], (new \DateTimeZone('+3:00')));
        $this->logger->pushHandler($handler);

        $this->jsonHandler = new JsonHandler();
        //
        $this->fileCutter = new FileCutter(static::STORAGE_DIR, $this->jsonHandler);

        $this->hotelRepository = new HotelRepository($this->entityManager);
        $this->locationRepository = new LocationRepository($this->entityManager);
        //
        $this->telegramNotifier = new TelegramNotifier();

        $this->initFileHandleData();

        $configuration = new Configuration();
        $this->rateHawkApi = new RatehawkApi(sprintf('%s:%s',
            $configuration->getKeyId(),
            $configuration->getApiKey(),
        ),
            static::STORAGE_DIR
        );
    }


    protected function initFileHandleData(): void
    {
        try {
            if (is_file(static::FILE_HANDLE_PATH)) {
                $this->fileHandleData = json_decode(file_get_contents(static::FILE_HANDLE_PATH), true, 512, JSON_THROW_ON_ERROR);
            }
            if (!isset($this->fileHandleData['lastRegion'])) {
                $this->fileHandleData['lastRegion'] = 0;
            }
            if (!isset($this->fileHandleData['lastHotel'])) {
                $this->fileHandleData['lastHotel'] = 0;
            }
            if (!isset($this->fileHandleData['needToSliceHotels'])) {
                $this->fileHandleData['needToSliceHotels'] = true;
            }
            if (!isset($this->fileHandleData['hotelsDumpDone'])) {
                $this->fileHandleData['hotelsDumpDone'] = false;
            }
            if (!isset($this->fileHandleData['currentHotelIncrement'])) {
                $this->fileHandleData['currentHotelIncrement'] = 0;
            }
        } catch (\Exception $e) {
            $this->fileHandleData = [
                'lastRegion' => 0,
                'lastHotel' => 0,
                'needToSliceHotels' => false,
                'hotelsDumpDone' => false,
                'currentHotelIncrement' => 0,
            ];
        }
    }

    protected function sliceHotelsFile(): void
    {
        $start = microtime(true);
        $this->telegramNotifier->notify('slicing hotels file');
        $this->fileCutter
            ->setFile(static::STORAGE_DIR . '/Hotels', $this->fileHandleData['lastHotel'], FileCutType::Hotels)
            ->sliceCurrentFile();

        $this->telegramNotifier->notify('slicing done in ' . (microtime(true) - $start));
        $this->fileHandleData['needToSliceHotels'] = false;
        $this->fileHandleData['currentHotelIncrement'] = 0;
        $this->saveFileHandleData();
    }

    protected function currentFileIsEmpty(string $fileName): bool
    {
        $this->jsonHandler->setFile(static::STORAGE_DIR . DIRECTORY_SEPARATOR . $fileName);
        if ($this->jsonHandler->getItem() === []) {
            $this->fileHandleData['hotelsDumpDone'] = true;
            $this->saveFileHandleData();
        }

        return $this->fileHandleData['hotelsDumpDone'];
    }

    public function handleHotelsDumpFile()
    {
        if ($this->fileHandleData['needToSliceHotels']) {
            $this->sliceHotelsFile();
            return;
        }
        if ($this->currentFileIsEmpty('Hotels_current')) {
            $this->telegramNotifier->notify('HOTELS DUMP IS DONE');
            return;
        }
        $fileHandlingStart = microtime(true);
        $this->jsonHandler->setFile(static::STORAGE_DIR . '/Hotels_current', $this->fileHandleData['currentHotelIncrement']);
        $start = microtime(true);
        $pointerTime = $start - $fileHandlingStart;
        if ($pointerTime < 0.1) {
            $pointerTime = 0;
        }
        $this->telegramNotifier->notify(sprintf('time to move pointer: %s', $pointerTime));
        $i = 0;
        $idx = $this->fileHandleData['currentHotelIncrement'];

        $temp = [];
        try {
            while ($hotelData = $this->jsonHandler->getItem()) {
                $temp[] = json_encode($hotelData);
                $this->hotelRepository->insertHotel($hotelData);
                $i++;
                $this->fileHandleData['currentHotelIncrement']++;
                var_dump($this->fileHandleData['currentHotelIncrement']);

                if ($i === 300) {
                    $this->hotelRepository->flush();
                    $this->hotelRepository->initEntities();
                    $this->saveFileHandleData();
                    $temp = [];

                    $i = 0;
                }
                if (microtime(true) - $fileHandlingStart > 265) {
                    $this->hotelRepository->flush();
                    $temp = [];
                    $this->saveFileHandleData();
                    $done = $this->fileHandleData['currentHotelIncrement'] - $idx;
                    $totalIdx = $this->fileHandleData['currentHotelIncrement'] + $this->fileHandleData['lastHotel'];
                    $this->telegramNotifier->notify(sprintf('DONE: %s, file offset: %s, total: %s', $done, $this->fileHandleData['currentHotelIncrement'], $totalIdx));
                    throw new \Exception('out of 265 seconds, time ' . (microtime(true) - $fileHandlingStart));
                }
            }
            if ($hotelData === []) {
                $totalIdx = $this->fileHandleData['currentHotelIncrement'] + $this->fileHandleData['lastHotel'];
                $this->fileHandleData['lastHotel'] += $this->fileHandleData['currentHotelIncrement'];
                $this->fileHandleData['needToSliceHotels'] = true;
                $this->hotelRepository->flush();
                $done = $this->fileHandleData['currentHotelIncrement'] - $idx;
                $this->telegramNotifier->notify(sprintf('DONE: %s, file offset: %s, total: %s', $done, $this->fileHandleData['currentHotelIncrement'], $totalIdx));
                $this->saveFileHandleData();
            }
        } catch (\Exception $e) {
            $this->saveFileHandleData();
            var_dump($e->getMessage());
            if (!empty($temp)) {
                file_put_contents(static::STORAGE_DIR . '/failed', implode(PHP_EOL, $temp) . PHP_EOL, FILE_APPEND);
            }
            $this->telegramNotifier->notify($e->getMessage());
        }
    }

    protected function saveFileHandleData(): void
    {
        file_put_contents(static::FILE_HANDLE_PATH, json_encode($this->fileHandleData));
    }
}
