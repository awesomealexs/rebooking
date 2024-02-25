<?php

namespace App\Handler;

class DeltaHandler extends BaseHandler
{
    protected const HOTELS_DELTA_FILE_NAME = 'Delta_hotels';

    protected const REVIEWS_DELTA_FILE_NAME = 'Delta_reviews';


    public function makeDelta(): void
    {
        if (!$this->deltaFileHandleData->isHotelsFile()) {
            $this->getHotelsDeltaFile();
            $this->deltaFileHandleData->setIsHotelsFile(true);
            $this->saveDeltaFileHandleData();
            return;
        }

        if (!$this->deltaFileHandleData->isHotelsDone()) {
            $this->handleHotelsFile();
            return;
        }

        if (!$this->deltaFileHandleData->isReviewsFile()) {
            $this->getReviewDeltaFile();
            $this->deltaFileHandleData->setIsReviewsFile(true);
            $this->saveDeltaFileHandleData();
            return;
        }

        if (!$this->deltaFileHandleData->isReviewsDone()) {
            $this->handleReviews();
            return;
        }

        $this->telegramNotifier->notify(sprintf('DELTA DONE, statistics:'));
        $this->purgeDeltaFileHandle();
    }

    protected function handleHotelsFile(): void
    {
        $this->deltaFileHandleData->setIsHotelsDone(true);
        $this->saveDeltaFileHandleData();
    }

    protected function getReviewDeltaFile(): bool
    {
        return true;
    }

    protected function handleReviews(): void
    {
        $this->deltaFileHandleData->setIsReviewsDone(true);
        $this->saveDeltaFileHandleData();
    }


    protected function getHotelsDeltaFile(): bool
    {
        try {
            $fileName = $this->rateHawkApi->getHotelsIncremental();
            $resultFileName = static::STORAGE_DIR . DIRECTORY_SEPARATOR . static::HOTELS_DELTA_FILE_NAME;
            $decompressed = preg_replace('/(.*)\..*/', '$1', $fileName);
            $command = "zstd -d {$fileName}; rm {$fileName};mv {$decompressed} $resultFileName";
            exec($command);

            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }
}
