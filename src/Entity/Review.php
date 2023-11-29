<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table('reviews')]
class Review
{
    #[Id]
    #[GeneratedValue('AUTO')]
    #[Column]
    private int $id;

    #[Column('hotel_id')]
    #[JoinColumn(referencedColumnName: 'id')]
    private int $hotelId;

    #[Column(name: 'review_plus', type: Types::TEXT, nullable: true)]
    private ?string $reviewPlus;

    #[Column(name: 'review_minus', type: Types::TEXT, nullable: true)]
    private ?string $reviewMinus;

    #[Column(name: 'created_at', nullable: true)]
    private ?string $createdAt;

    #[Column(nullable: true)]
    private ?string $author;

    #[Column(nullable: true)]
    private ?int $adults;

    #[Column(nullable: true)]
    private ?int $children;

    #[Column(nullable: true)]
    private ?string $roomName;

    #[Column(nullable: true)]
    private ?int $nights;

    #[Column(name: 'traveller_type', nullable: true)]
    private ?string $travellerType;

    #[Column(name: 'trip_type', nullable: true)]
    private ?string $tripType;

    #[Column(type: Types::DECIMAL, nullable: true)]
    private ?float $rating;

    #[Column(nullable: true)]
    private ?int $cleanness;

    #[Column(nullable: true)]
    private ?int $location;

    #[Column(nullable: true)]
    private ?int $price;

    #[Column(nullable: true)]
    private ?int $services;

    #[Column(nullable: true)]
    private ?int $room;

    #[Column(nullable: true)]
    private ?int $meal;

    #[Column(nullable: true)]
    private ?string $wifi;

    #[Column(nullable: true)]
    private ?string $hygiene;

    #[ManyToOne(targetEntity: Hotel::class, inversedBy: 'reviews', cascade: ['persist', 'remove'])]
    #[JoinColumn(referencedColumnName: 'id')]
    private Hotel $hotel;

    public function getHotel(): Hotel
    {
        return $this->hotel;
    }

    public function setHotel(Hotel $hotel): Review
    {
        $this->hotel = $hotel;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getHotelId(): int
    {
        return $this->hotelId;
    }

    public function setHotelId(int $hotelId): Review
    {
        $this->hotelId = $hotelId;
        return $this;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): Review
    {
        $this->author = $author;
        return $this;
    }

    public function getHygiene(): ?string
    {
        return $this->hygiene;
    }

    public function setHygiene(?string $hygiene): Review
    {
        $this->hygiene = $hygiene;
        return $this;
    }

    public function getReviewPlus(): ?string
    {
        return $this->reviewPlus;
    }


    public function setReviewPlus(?string $reviewPlus): Review
    {
        $this->reviewPlus = $reviewPlus;
        return $this;
    }

    public function getReviewMinus(): ?string
    {
        return $this->reviewMinus;
    }

    public function setReviewMinus(?string $reviewMinus): Review
    {
        $this->reviewMinus = $reviewMinus;
        return $this;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): Review
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getAdults(): ?int
    {
        return $this->adults;
    }

    public function setAdults(?int $adults): Review
    {
        $this->adults = $adults;
        return $this;
    }

    public function getChildren(): ?int
    {
        return $this->children;
    }

    public function setChildren(?int $children): Review
    {
        $this->children = $children;
        return $this;
    }

    public function getRoomName(): ?string
    {
        return $this->roomName;
    }

    public function setRoomName(?string $roomName): Review
    {
        $this->roomName = $roomName;
        return $this;
    }

    public function getNights(): ?int
    {
        return $this->nights;
    }

    public function setNights(?int $nights): Review
    {
        $this->nights = $nights;
        return $this;
    }

    public function getTravellerType(): ?string
    {
        return $this->travellerType;
    }

    public function setTravellerType(?string $travellerType): Review
    {
        $this->travellerType = $travellerType;
        return $this;
    }

    public function getTripType(): ?string
    {
        return $this->tripType;
    }

    public function setTripType(?string $tripType): Review
    {
        $this->tripType = $tripType;
        return $this;
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }

    public function setRating(?float $rating): Review
    {
        $this->rating = $rating;
        return $this;
    }


    public function getCleanness(): ?int
    {
        return $this->cleanness;
    }

    public function setCleanness(?int $cleanness): Review
    {
        $this->cleanness = $cleanness;
        return $this;
    }

    public function getLocation(): ?int
    {
        return $this->location;
    }

    public function setLocation(?int $location): Review
    {
        $this->location = $location;
        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): Review
    {
        $this->price = $price;
        return $this;
    }

    public function getServices(): ?int
    {
        return $this->services;
    }

    public function setServices(?int $services): Review
    {
        $this->services = $services;
        return $this;
    }

    public function getRoom(): ?int
    {
        return $this->room;
    }

    public function setRoom(?int $room): Review
    {
        $this->room = $room;
        return $this;
    }

    public function getMeal(): ?int
    {
        return $this->meal;
    }

    public function setMeal(?int $meal): Review
    {
        $this->meal = $meal;
        return $this;
    }

    public function getWifi(): ?string
    {
        return $this->wifi;
    }

    public function setWifi(?string $wifi): Review
    {
        $this->wifi = $wifi;
        return $this;
    }
}
