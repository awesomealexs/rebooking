<?php

namespace App\Repository;

use App\Entity\Hotel;
use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class ReviewRepository
{
    protected EntityManagerInterface $entityManager;

    protected EntityRepository $hotelRepository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        $this->hotelRepository = $this->entityManager->getRepository(Hotel::class);
    }

    public function insertReview(array $review, string $hotelUri): void
    {
       $hotel = $this->hotelRepository->findOneBy(['uri' => $hotelUri]);
       if($hotel === null){
           return;
       }

       $review = (new Review())
           ->setHotel($hotel)
           ->setReviewPlus($review['review_plus'])
           ->setReviewMinus($review['review_minus'])
           ->setCreatedAt($review['created'])
           ->setAuthor($review['author'])
           ->setAdults($review['adults'])
           ->setChildren($review['children'])
           ->setRoomName($review['room_name'])
           ->setNights($review['nights'])
           ->setTravellerType($review['traveller_type'])
           ->setTripType($review['trip_type'])
           ->setRating($review['rating'])
           ->setCleannessRating($review['detailed']['cleanness'])
           ->setLocationRating($review['detailed']['location'])
           ->setPriceRating($review['detailed']['price'])
           ->setServicesRating($review['detailed']['services'])
           ->setRoomRating($review['detailed']['room'])
           ->setMealRating($review['detailed']['meal'])
           ->setWifiRating($review['detailed']['wifi'])
           ->setHygieneRating($review['detailed']['hygiene'])
       ;

        $this->entityManager->persist($review);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}

