<?php

namespace App\Dto;

class DeltaFileHandleData
{
    protected bool $isHotelsFile = false;

    protected bool $isHotelsDone = false;

    protected bool $isReviewsFile = false;

    protected bool $isReviewsDone = false;

    public function isHotelsFile(): bool
    {
        return $this->isHotelsFile;
    }

    public function setIsHotelsFile(bool $isHotelsFile): DeltaFileHandleData
    {
        $this->isHotelsFile = $isHotelsFile;

        return $this;
    }

    public function isHotelsDone(): bool
    {
        return $this->isHotelsDone;
    }

    public function setIsHotelsDone(bool $isHotelsDone): DeltaFileHandleData
    {
        $this->isHotelsDone = $isHotelsDone;
        return $this;
    }

    public function isReviewsFile(): bool
    {
        return $this->isReviewsFile;
    }

    public function setIsReviewsFile(bool $isReviewsFile): DeltaFileHandleData
    {
        $this->isReviewsFile = $isReviewsFile;
        return $this;
    }

    public function isReviewsDone(): bool
    {
        return $this->isReviewsDone;
    }

    public function setIsReviewsDone(bool $isReviewsDone): DeltaFileHandleData
    {
        $this->isReviewsDone = $isReviewsDone;
        return $this;
    }
}
