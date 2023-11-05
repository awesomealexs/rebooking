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

    #[Column]
    private int $stars;

    #[Column]
    private string $title;

    #[Column(type: Types::TEXT)]
    private string $text;

    #[Column]
    private string $author;

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

    public function getStars(): int
    {
        return $this->stars;
    }

    public function setStars(int $stars): Review
    {
        $this->stars = $stars;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): Review
    {
        $this->title = $title;
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): Review
    {
        $this->text = $text;
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


}