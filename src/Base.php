<?php

namespace App;
require_once __DIR__ . '/bootstrap.php';

use App\Entity\Hotel;
use App\Enum\FileCutType;
use App\Helper\FileCutter;
use App\Helper\JsonHandler;
use App\Notify\TelegramNotifier;
use App\RatehawkApi\Configuration;
use App\RatehawkApi\RatehawkApi;
use App\Repository\HotelRepository;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManager;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class Base
{
    public const BASE_DIR = __DIR__;

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

    protected array $fileHandleData;

    public function __construct()
    {


        $params = [
            'driver' => getenv('DB_DRIVER'),
            'user' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
            'dbname' => getenv('DB_NAME'),
            'host' => getenv('DB_HOST'),
            'port' => getenv('DB_PORT'),
        ];
        //var_dump($params);die;
        //$dsn = sprintf('mysql:dbname=%s;host=%s;port=%s',
        //getenv('DB_NAME'),
        //getenv('DB_HOST'),
        //getenv('DB_PORT'),
        //);
        ////var_dump($dsn);die;
        ////$dsn = 'mysql:dbname=rebooking;host=51.159.11.200;port=5353';
        //var_dump(getenv('DB_PASSWORD'));
        //$pdo = new \PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'));
        //
        //var_dump($pdo);
        //$q = $pdo->query("SELECT * FROM test", \PDO::FETCH_ASSOC);
        //$rows = $q->fetchAll();
        //var_dump($rows);
        //
        //
        //
        //die;

        $isDevMode = true;

        //$dsnParser = new DsnParser();
        //$connectionParams = $dsnParser
        //    ->parse('pdo-mysql://51.159.11.200:4486/rebooking?charset=utf8mb4');
        //
        //$conn = DriverManager::getConnection($connectionParams);

        //$em = new EntityManager($conn, \Doctrine\ORM\Tools\Setup::createAttributeMetadataConfiguration([__DIR__ . "/Entety"]));
        //$loc = $em->getRepository(Location::class);
        //$loc->count(['id']);

        $this->entityManager = EntityManager::create($params, \Doctrine\ORM\Tools\Setup::createAttributeMetadataConfiguration([__DIR__ . "/Entity"], $isDevMode));

        $logDir = static::BASE_DIR . '/Logs/ApiLog';
        $handler = new RotatingFileHandler($logDir);

        $this->logger = new Logger('', [], [], (new \DateTimeZone('+3:00')));
        $this->logger->pushHandler($handler);

        $this->jsonHandler = new JsonHandler();

        $this->fileCutter = new FileCutter(static::STORAGE_DIR, $this->jsonHandler);


        $this->hotelRepository = new HotelRepository($this->entityManager);
        $this->locationRepository = new LocationRepository($this->entityManager);

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

    protected function initEntityManager()
    {
        $params = [
            'driver' => getenv('DB_DRIVER'),
            'user' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
            'dbname' => getenv('DB_NAME'),
            'host' => getenv('DB_HOST'),
        ];
        $isDevMode = true;
        $this->entityManager = EntityManager::create($params, \Doctrine\ORM\Tools\Setup::createAttributeMetadataConfiguration([__DIR__ . "/Ratehawk/Enteties"], $isDevMode));
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

    protected function saveFileHandleData(): void
    {
        file_put_contents(static::FILE_HANDLE_PATH, json_encode($this->fileHandleData));
    }

    public function getRegionsDumpFile()
    {
        try {
            $fileName = $this->rateHawkApi->getRegionDump();
            $resultFileName = static::STORAGE_DIR . DIRECTORY_SEPARATOR . 'Regions';
            $decompressed = preg_replace('/(.*)\..*/', '$1', $fileName);
            $command = "zstd -d {$fileName}; rm {$fileName};mv {$decompressed} $resultFileName";
            exec($command);


            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function handleRegionsDumpFile()
    {
        $start = microtime(true);
        $this->jsonHandler->setFile(static::STORAGE_DIR . '/Regions', $this->fileHandleData['lastRegion']);
        $startPos = $this->fileHandleData['lastRegion'];
        try {
            while ($regionData = $this->jsonHandler->getItem()) {
                $this->locationRepository->insertRegion($regionData);
                $this->fileHandleData['lastRegion']++;
                var_dump($this->fileHandleData['lastRegion']);

                //$this->saveFileHandleData();
                //
                if ($this->fileHandleData['lastRegion'] - $startPos > 30000) {

                    throw new \Exception('');
                }
            }
        } catch (\Exception $e) {

        }
        $this->locationRepository->flush();

        $time = microtime(true) - $start;
        var_dump($time);

        $done = $this->fileHandleData['lastRegion'] - $startPos;
        $this->telegramNotifier->notify('done: ' . $done . ' time: ' . $time);
        $this->saveFileHandleData();
    }


    public function getHotelsDumpFile(): bool
    {
        try {
            $fileName = $this->rateHawkApi->getHotelsDump();
            $resultFileName = static::STORAGE_DIR . DIRECTORY_SEPARATOR . 'Hotels';
            $decompressed = preg_replace('/(.*)\..*/', '$1', $fileName);
            $command = "zstd -d {$fileName}; rm {$fileName};mv {$decompressed} $resultFileName";
            exec($command);


            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function test()
    {
        //$this->telegramNotifier->notify(123);

        die;
        $json = file_get_contents(static::STORAGE_DIR . '/junk.json');
        $data = json_decode($json, true);
        $address = $data['address'];

        var_dump($address);

        var_dump(preg_replace('/[^а-яА-ЯёЁa-zA-Z0-9\-\,[:space:]]/u', '', $address));


        die;

        $this->jsonHandler->setFile(static::STORAGE_DIR . '/Hotels_current', $this->fileHandleData['currentHotelIncrement']);
        $data = $this->jsonHandler->getItem(true);
        var_dump($data);
        die;

        $i = 0;
        while ($i !== 500) {
            $array[] = $this->jsonHandler->getItem();
            $i++;
        }
        file_put_contents(static::STORAGE_DIR . '/dump.log', var_export($array, true));
        die;

        $this->initEntityManager();
        $hotelRep = $this->entityManager->getRepository(Hotel::class);

        $hotel = $hotelRep->findOneBy(['uri' => 'apparthotel7sensation']);

        $room = ($hotel->getRooms()->current());
        $images = [];
        foreach ($room->getImages()->getIterator() as $item) {
            $images[] = $item->getImage();
        }

        return [
            $hotel->getTitle(),
            $hotel->getAddress(),
            'rooms' => [
                $room->getTitle() => $images
            ]
        ];
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

    public function saveAmenities(): void
    {
        if ($this->currentFileIsEmpty('Hotels')) {
            $this->telegramNotifier->notify('HOTELS DUMP IS DONE');
            return;
        }
        $fileHandlingStart = microtime(true);
        $this->jsonHandler->setFile(static::STORAGE_DIR . '/Hotels', $this->fileHandleData['lastHotel']);

        $start = microtime(true);
        $pointerTime = $start - $fileHandlingStart;
        if ($pointerTime < 0.1) {
            $pointerTime = 0;
        }
        $this->telegramNotifier->notify(sprintf('time to move pointer: %s', $pointerTime));
        $idx = $this->fileHandleData['lastHotel'];
        try {
            while ($hotelData = $this->jsonHandler->getItem()) {
                $this->hotelRepository->saveAmenities($hotelData['amenity_groups']);
                $this->fileHandleData['lastHotel']++;
                var_dump($this->fileHandleData['lastHotel']);


                if (microtime(true) - $fileHandlingStart > 980) {

                    $this->saveFileHandleData();
                    $done = $this->fileHandleData['lastHotel'] - $idx;
                    $totalIdx = $this->fileHandleData['lastHotel'];
                    $this->telegramNotifier->notify(sprintf('DONE: %s, total: %s', $done, $totalIdx));
                    throw new \Exception('out of 980 seconds, time ' . (microtime(true) - $fileHandlingStart));
                }
            }
            if ($hotelData === []) {
                $totalIdx = $this->fileHandleData['lastHotel'];
                $done = $this->fileHandleData['lastHotel'] - $idx;
                $this->telegramNotifier->notify(sprintf('DONE: %s, total: %s', $done, $totalIdx));
                $this->saveFileHandleData();
            }
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->telegramNotifier->notify($e->getMessage());
        }
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

                if ($i === 100) {
                    $this->hotelRepository->flush();
                    $this->hotelRepository->initEntities();
                    $this->saveFileHandleData();
                    $temp = [];

                    $i = 0;
                }
                if (microtime(true) - $fileHandlingStart > 540) {
                    $this->hotelRepository->flush();
                    $temp = [];
                    $this->saveFileHandleData();
                    $done = $this->fileHandleData['currentHotelIncrement'] - $idx;
                    $totalIdx = $this->fileHandleData['currentHotelIncrement'] + $this->fileHandleData['lastHotel'];
                    $this->telegramNotifier->notify(sprintf('DONE: %s, file offset: %s, total: %s', $done, $this->fileHandleData['currentHotelIncrement'], $totalIdx));
                    throw new \Exception('out of 540 seconds, time ' . (microtime(true) - $fileHandlingStart));
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
            var_dump($e->getMessage());
            if (!empty($temp)) {
                file_put_contents(static::STORAGE_DIR . '/failed', implode(PHP_EOL, $temp) . PHP_EOL, FILE_APPEND);
            }
            $this->telegramNotifier->notify($e->getMessage());
        }
    }
}


