<?php

namespace App\Handler;

use App\Dto\FileHandleData;
use App\Helper\FileCutter;
use App\Helper\JsonHandler;
use App\Notify\TelegramNotifier;
use App\RatehawkApi\Configuration;
use App\RatehawkApi\RatehawkApi;
use App\Repository\HotelRepository;
use App\Repository\LocationRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

abstract class BaseHandler
{
    public const BASE_DIR = __DIR__ . '/../../';

    public const STORAGE_DIR = self::BASE_DIR . '/src/Storage';

    protected const FILE_HANDLE_PATH = self::STORAGE_DIR . '/fileHandle';

    protected RatehawkApi $rateHawkApi;

    protected Logger $logger;

    protected JsonHandler $jsonHandler;

    protected EntityManagerInterface $entityManager;

    protected HotelRepository $hotelRepository;

    protected LocationRepository $locationRepository;

    protected ReviewRepository $reviewRepository;

    protected FileCutter $fileCutter;

    protected TelegramNotifier $telegramNotifier;

    protected FileHandleData $fileHandleData;

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
        $this->reviewRepository = new ReviewRepository($this->entityManager);

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
        if (is_file(static::FILE_HANDLE_PATH)) {
            $this->fileHandleData = unserialize(file_get_contents(static::FILE_HANDLE_PATH), ['allowed_classes' => [FileHandleData::class]]);
            return;
        }
        $this->fileHandleData = new FileHandleData();
    }

    protected function saveFileHandleData(): void
    {
        file_put_contents(static::FILE_HANDLE_PATH, serialize($this->fileHandleData));
    }
}
