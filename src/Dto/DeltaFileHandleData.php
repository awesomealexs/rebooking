<?php

namespace App\Dto;

class DeltaFileHandleData
{
    private int $lastRegion = 0;

    private int $lastHotel = 0;

    private bool $needToSliceHotels = false;

    private bool $hotelsDumpDone = false;

    private int $currentHotelIncrement = 0;

    private string $lastReviewHotelName = '';

    public function incrementLastRegion(): DeltaFileHandleData
    {
        $this->lastRegion++;
        return $this;
    }

    public function incrementCurrentHotel(): DeltaFileHandleData
    {
        $this->currentHotelIncrement++;
        return $this;
    }

    public function getLastRegion(): int
    {
        return $this->lastRegion;
    }

    public function setLastRegion(int $lastRegion): DeltaFileHandleData
    {
        $this->lastRegion = $lastRegion;
        return $this;
    }

    public function getLastHotel(): int
    {
        return $this->lastHotel;
    }

    public function setLastHotel(int $lastHotel): DeltaFileHandleData
    {
        $this->lastHotel = $lastHotel;
        return $this;
    }

    public function isNeedToSliceHotels(): bool
    {
        return $this->needToSliceHotels;
    }

    public function setNeedToSliceHotels(bool $needToSliceHotels): DeltaFileHandleData
    {
        $this->needToSliceHotels = $needToSliceHotels;
        return $this;
    }

    public function isHotelsDumpDone(): bool
    {
        return $this->hotelsDumpDone;
    }

    public function setHotelsDumpDone(bool $hotelsDumpDone): DeltaFileHandleData
    {
        $this->hotelsDumpDone = $hotelsDumpDone;
        return $this;
    }

    public function getCurrentHotelIncrement(): int
    {
        return $this->currentHotelIncrement;
    }


    public function setCurrentHotelIncrement(int $currentHotelIncrement): DeltaFileHandleData
    {
        $this->currentHotelIncrement = $currentHotelIncrement;
        return $this;
    }

    public function getLastReviewHotelName(): string
    {
        return $this->lastReviewHotelName;
    }

    public function setLastReviewHotelName(string $lastReviewHotelName): DeltaFileHandleData
    {
        $this->lastReviewHotelName = $lastReviewHotelName;

        return $this;
    }
}
