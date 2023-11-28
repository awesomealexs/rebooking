<?php

namespace App\Handler;

use App\Enum\FileCutType;

class ReviewHandler extends BaseHandler
{
    protected const REVIEWS_FILE_NAME = 'Reviews';

    protected const HOTELS_CURRENT_FILE_NAME = 'Reviews_current';

    public function getReviewsDumpFile(): bool
    {
        try {
            $fileName = $this->rateHawkApi->getReviewsDump();
            $resultFileName = static::STORAGE_DIR . DIRECTORY_SEPARATOR . static::REVIEWS_FILE_NAME;
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
