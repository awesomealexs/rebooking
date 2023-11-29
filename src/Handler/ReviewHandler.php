<?php

namespace App\Handler;

use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

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

    public function handleReviewsFile()
    {
        $hotelsData = Items::fromFile(static::STORAGE_DIR . DIRECTORY_SEPARATOR . self::REVIEWS_FILE_NAME, ['decoder' => new ExtJsonDecoder(true)]);

        foreach($hotelsData as $hotelUri => $reviewData){
            if($reviewData === null){
                continue;
            }
            foreach($reviewData['reviews'] as $review){
                $this->reviewRepository->insertReview($review, $hotelUri);
            }
            $this->reviewRepository->flush();
            die;
        }
    }
}
