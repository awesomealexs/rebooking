<?php

namespace App\Handler;

use App\Helper\FileCutter;
use App\Helper\JsonHandler;
use App\Notify\TelegramNotifier;
use App\RatehawkApi\Configuration;
use App\RatehawkApi\RatehawkApi;
use App\Repository\HotelRepository;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use App\Entity\Location;

abstract class BaseHandler
{
    public const BASE_DIR = __DIR__ . '/../../';

    public const STORAGE_DIR = self::BASE_DIR . '/src/Storage';

    protected const FILE_HANDLE_PATH = self::STORAGE_DIR . '/fileHandle.json';

    protected RatehawkApi $rateHawkApi;

    protected Logger $logger;

    protected JsonHandler $jsonHandler;

    protected EntityManagerInterface $entityManager;

    protected HotelRepository $hotelRepository;

    protected LocationRepository $locationRepository;

    protected FileCutter $fileCutter;

    protected TelegramNotifier $telegramNotifier;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

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
}
