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
            var_dump($hotelUri);
            foreach($reviewData['reviews'] as $review){
                if(!empty($review['images'])){
                    $this->reviewRepository->insertReviews($reviewData, $hotelUri);
                    $this->reviewRepository->flush();
                    return;
                    //var_dump($review['images']);
                    //die;
                    //file_put_contents(static::STORAGE_DIR.'/dsfdsscdwfewfdsf', json_encode($review));
                    //return;
                }
                continue;
            }

            //$this->reviewRepository->insertReviews($reviewData, $hotelUri);

            //$this->reviewRepository->flush();
            //return;
        }
    }
}
