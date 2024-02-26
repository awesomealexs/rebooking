<?php

namespace App\Handler;

use App\Entity\Delta;
use App\Enum\HotelsDelta;

class DeltaHandler extends BaseHandler
{
    protected const HOTELS_DELTA_FILE_NAME = 'Delta_hotels';

    protected const REVIEWS_DELTA_FILE_NAME = 'Delta_reviews';

    protected const DELTA_REVIEWS_FILEPATH = self::STORAGE_DIR.DIRECTORY_SEPARATOR .self::REVIEWS_DELTA_FILE_NAME;
    protected const DELTA_HOTELS_FILEPATH = self::STORAGE_DIR.DIRECTORY_SEPARATOR .self::HOTELS_DELTA_FILE_NAME;

    protected const TWO_DAYS = 48 * 3600;

    protected function isNeedStartDelta(): void
    {
        if ($this->deltaFileHandleData->isDeltaInProgress()) {
            return;
        }
        if (time() - $this->deltaFileHandleData->getDeltaDoneTimestamp() > self::TWO_DAYS) {
            $this->purgeDeltaFileHandle();
            $this->initDeltaFileHandleData();
            $this->saveDeltaFileHandleData();
        }
    }

    public function makeDelta(): void
    {
        $this->isNeedStartDelta();

        if (!$this->deltaFileHandleData->isHotelsFile()) {
            $deltaRepository = $this->entityManager->getRepository(Delta::class);
            $delta = $deltaRepository->findOneBy([], ['id' => 'DESC']);
            $lastUpdated = '';
            if ($delta !== null) {
                $lastUpdated = $delta->getLastUpdate();
            }

            $this->telegramNotifier->notify('GETTING DELTA HOTELS FILE');
            $this->getHotelsDeltaFile($lastUpdated);
            $this->deltaFileHandleData->setIsHotelsFile(true);
            $this->saveDeltaFileHandleData();
            return;
        }

        if (!$this->deltaFileHandleData->isHotelsDone()) {
            $this->telegramNotifier->notify('HOTELS DELTA');
            $this->handleHotelsFile();
            return;
        }

//        if (!$this->deltaFileHandleData->isReviewsFile()) {
//            $this->telegramNotifier->notify('GETTING DELTA REVIEWS FILE');
//            $this->getReviewDeltaFile();
//            $this->deltaFileHandleData->setIsReviewsFile(true);
        ////            $this->saveDeltaFileHandleData();
//            return;
//        }

        if (!$this->deltaFileHandleData->isReviewsDone()) {
            $this->telegramNotifier->notify('REVIEWS DELTA');
            $this->handleReviews();
            return;
        }

        $this->deltaFileHandleData->setDeltaInProgress(false);
        $this->telegramNotifier->notify(sprintf('DELTA DONE, statistics:'));
//        $this->purgeDeltaFiles();
    }

    protected function purgeDeltaFiles(): void
    {
        unlink(self::DELTA_HOTELS_FILEPATH);
        unlink(self::DELTA_REVIEWS_FILEPATH);
    }

    protected function handleHotelsFile(): void
    {
        $start = microtime(true);
        $this->jsonHandler->setFile(self::DELTA_HOTELS_FILEPATH);

        while ($hotelDta = $this->jsonHandler->getItem()) {
            $result = $this->hotelRepository->updateHotel($hotelDta);
            switch ($result) {
                case $result === HotelsDelta::Inserted:
                    $this->deltaFileHandleData->increaseHotelsCreated();
                    break;
                case $result === HotelsDelta::Updated:
                    $this->deltaFileHandleData->increaseHotelsUpdated();
                    break;
                case $result === HotelsDelta::Deleted:
                    $this->deltaFileHandleData->increaseHotelsDeleted();
                    break;
            }
        }
//        $this->deltaFileHandleData->setIsHotelsDone(true);
//        $this->saveDeltaFileHandleData();
    }

    protected function getReviewDeltaFile(): bool
    {
        try {
            $fileName = $this->rateHawkApi->getReviewsIncremental();
            $resultFileName = self::DELTA_REVIEWS_FILEPATH;
            $decompressed = preg_replace('/(.*)\..*/', '$1', $fileName);
            $command = "zstd -d {$fileName}; rm {$fileName};mv {$decompressed} $resultFileName";
            exec($command);

            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    protected function handleReviews(): void
    {
        $this->deltaFileHandleData->setIsReviewsDone(true);
        $this->saveDeltaFileHandleData();
    }


    protected function getHotelsDeltaFile(string $lastUpdated): bool
    {
        try {
            $fileData = $this->rateHawkApi->getHotelsIncremental($lastUpdated);
            if ($fileData === null) {
                $this->logger->debug('No new updates on hotels delta');
                $this->telegramNotifier->notify('No new updates on hotels delta');
                $this->deltaFileHandleData->setIsReviewsDone(true);
                return true;
            }
            $fileName = $fileData['filename'];

            $resultFileName = self::DELTA_HOTELS_FILEPATH;
            $decompressed = preg_replace('/(.*)\..*/', '$1', $fileName);
            $command = "zstd -d {$fileName}; rm {$fileName};mv {$decompressed} $resultFileName";
            exec($command);

            $newDelta = (new Delta())
                ->setLastUpdate($fileData['last_update']);
            $this->entityManager->persist($newDelta);
            $this->entityManager->flush();


            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }
}
