<?php

namespace App\Controller;

use App\Entity\Hotel;
use App\Entity\HotelAmenities;
use App\Entity\HotelDescription;
use App\Entity\HotelImage;
use App\Entity\Location;
use App\Entity\Review;
use App\Entity\Room;
use App\Entity\RoomAmenities;
use App\Helper\StringHelper;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HotelsController extends AbstractController
{
    protected Logger $logger;

    protected EntityManagerInterface $entityManager;

    protected const HOTELS_PER_PAGE = 30;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/hotels2loc/{location}', name: 'app_hotels_by_oc', methods: ["GET"])]
    public function hotelsByLocations(Request $request, $location): JsonResponse
    {
        $locationRep = $this->entityManager->getRepository(Location::class);
        $locations = $locationRep->findBy([
            'title' => $location,
            'type' => 'City'
        ]);

        $page = $request->query->get('page') ?? 1;
        $perPage = $request->query->get('per_page') ?? static::HOTELS_PER_PAGE;


        $locationId = $locations[0]->getId();

        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('h.id')
            ->from('App\Entity\Hotel', 'h')
            ->where('h.locationId=?1')
            ->orderBy('h.starRating', 'DESC')
            ->setParameter(1, $locationId)
            ->setFirstResult($perPage * $page)
            ->setMaxResults($perPage)
            ->getQuery();

        $ids = array_map(static function ($item) {
            return $item['id'];
        }, $query->getResult());

        $hotelsRepository = $this->entityManager->getRepository(Hotel::class);


        $hotels = [];
        foreach ($hotelsRepository->findBy(['id' => $ids]) as $hotelIem) {
            if ($hotelIem instanceof Hotel) {
                $amenities = [];
                foreach ($hotelIem->getAmenities()->getIterator() as $amenity) {
                    if ($amenity instanceof HotelAmenities) {
                        $amenities[] = $amenity->getGroup()->getName();
                    }
                }

                $image = $hotelIem->getImages()->get(0)?->getImage();
                $amenities = array_values(array_unique($amenities));
                $hotels[] = [
                    'uri' => $hotelIem->getUri(),
                    'title' => $hotelIem->getTitle(),
                    'address' => $hotelIem->getAddress(),
                    'star_rating' => $hotelIem->getStarRating(),
                    'amenities' => $amenities,
                    'image' => StringHelper::replaceWithinBracers($image ?? '', 'size', '1024x768'),
                ];
            }
        }


        return $this->json([
            'success' => true,
            'data' => [
                'region_id' => $locationId,
                'hotels' => $hotels,
                ],
        ]);
    }


    #[Route('/hotel/{uri}', name: 'app_hotels', methods: ["GET"])]
    public function hotelInfo(Request $request, $uri): JsonResponse
    {
        $hotelRepository = $this->entityManager->getRepository(Hotel::class);
        $hotel = ($hotelRepository->findOneBy(['uri' => $uri]));
        if ($hotel === null) {
            return $this->json([
                'success' => false,
                'message' => 'not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $rooms = [];
        foreach ($hotel?->getRooms()->getIterator() as $room) {
            if ($room instanceof Room) {
                $roomImages = [];
                foreach ($room->getImages()->getIterator() as $roomImage) {
                    $roomImages[] = StringHelper::replaceWithinBracers($roomImage->getImage(), 'size', '1024x768');
                }
                $roomAmenities = [];
                foreach ($room->getAmenities()->getIterator() as $amenity) {
                    if ($amenity instanceof RoomAmenities) {
                        $roomAmenities[] = $amenity->getName();
                    }
                }
                $rooms[] = [
                    'title' => $room->getTitle(),
                    'images' => $roomImages,
                    'amenities' => $roomAmenities,
                    'ratehawk_room_group' => $room->getRoomGroup()
                ];
            }
        }
        $hotelImages = [];
        foreach ($hotel?->getImages()->getIterator() as $hotelImage) {
            if ($hotelImage instanceof HotelImage) {
                $hotelImages[] = StringHelper::replaceWithinBracers($hotelImage->getImage(), 'size', '1024x768');
            }
        }

        $hotelAmenities = [];
        foreach ($hotel?->getAmenities()->getIterator() as $hotelAmenity) {
            if ($hotelAmenity instanceof HotelAmenities) {
                $hotelAmenities[$hotelAmenity->getGroup()->getName()][] = $hotelAmenity->getName();
            }
        }
        $hotelDescriptions = [];
        foreach ($hotel?->getDescriptions()->getIterator() as $hotelDescription) {
            if ($hotelDescription instanceof HotelDescription) {
                $hotelDescriptions[$hotelDescription->getDescriptionGroup()->getTitle()] = $hotelDescription->getText();
            }
        }

        $reviews = [];


        $hotel2 = $hotelRepository->findOneBy(['uri' => 'ozgur_bey_spa_hotel_']);

        foreach($hotel2?->getReviews()->getIterator() as $review){
            if($review instanceof Review){
                $reviews['rating'] = $hotel2?->getClientRating();
                $reviews['detailed_ratings']['cleanness'] = $hotel2?->getCleannessRating();
                $reviews['detailed_ratings']['location'] = $hotel2?->getLocationRating();
                $reviews['detailed_ratings']['price'] = $hotel2?->getPriceRating();
                $reviews['detailed_ratings']['services'] = $hotel2?->getServicesRating();
                $reviews['detailed_ratings']['room'] = $hotel2?->getRoomRating();
                $reviews['detailed_ratings']['meal'] = $hotel2?->getMealRating();
                $reviews['detailed_ratings']['wifi'] = $hotel2?->getWifiRating();
                $reviews['detailed_ratings']['hygiene'] = $hotel2?->getHygieneRating();

                $reviews['reviews'][] = [
                    'review_plus' => $review->getReviewPlus(),
                    'review_minus' => $review->getReviewMinus(),
                    'created' => $review->getCreatedAt(),
                    'author' => $review->getAuthor(),
                    'adults' => $review->getAdults(),
                    'children' => $review->getChildren(),
                    'room_name' => $review->getRoomName(),
                    'nights' => $review->getNights(),
                    'images' => [],
                    'detailed' => [
                        'cleanness' => $review->getCleannessRating(),
                        'location' => $review->getLocationRating(),
                        'price' => $review->getPriceRating(),
                        'services' => $review->getServicesRating(),
                        'room' => $review->getRoomRating(),
                        'meal' => $review->getMealRating(),
                        'wifi' => $review->getWifiRating(),
                        'hygiene' => $review->getHygieneRating(),
                    ],
                    'traveller_type' => $review->getTravellerType(),
                    'trip_type' => $review->getTripType(),
                    'rating' => $review->getRating(),
                ];
            }
        }

        $hotelData = [
            'id' => $hotel->getUri(),
            'title' => $hotel->getTitle(),
            'address' => $hotel->getAddress(),
            'region_id' => $hotel->getLocation()->getRateHawkId(),
            'location' => $hotel->getLocation()->getTitle(),
            'phone' => $hotel->getPhone(),
            'email' => $hotel->getEmail(),
            'check_in' => $hotel->getCheckIn(),
            'check_out' => $hotel->getCheckOut(),
            'star_rating' => $hotel->getStarRating(),
            'latitude' => $hotel->getLatitude(),
            'longitude' => $hotel->getLongitude(),
            'additional_information' => $hotel->getAdditionalInformation(),
            'reviews' => $reviews,
            'images' => $hotelImages,
            'amenities' => $hotelAmenities,
            'descriptions' => $hotelDescriptions,
            'rooms' => $rooms,
        ];


        return $this->json([
            'success' => true,
            'data' => $hotelData
        ]);
    }
}
