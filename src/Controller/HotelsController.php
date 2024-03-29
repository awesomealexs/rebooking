<?php

namespace App\Controller;

use App\Entity\Hotel;
use App\Entity\HotelAmenities;
use App\Entity\HotelDescription;
use App\Entity\HotelImage;
use App\Entity\Location;
use App\Entity\Review;
use App\Entity\ReviewImage;
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

    #[Route('/hotels2loc/{countryCode}/{location}', name: 'app_hotels_by_oc', methods: ["GET"])]
    public function hotelsByLocations(Request $request, string $countryCode, string $location): JsonResponse
    {
        $locationRep = $this->entityManager->getRepository(Location::class);
        $locations = $locationRep->findBy([
            'countryCode' => $countryCode,
            'title' => $location,
            'type' => 'City'
        ]);

        $page = $request->query->get('page') ?? 1;
        $perPage = $request->query->get('per_page') ?? static::HOTELS_PER_PAGE;


        $currentLocation = $locations[0];
        $locationId = $currentLocation->getId();

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

        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('count(h.id)')
            ->from('App\Entity\Hotel', 'h')
            ->where('h.locationId=?1')
            ->setParameter(1, $locationId)
            ->getQuery();

        $totalHotels = array_pop($query->getResult()[0]);


        $hotels = [];
        foreach ($hotelsRepository->findBy(['id' => $ids]) as $hotelIem) {
            /**
             * @var $hotelIem Hotel
             */
            $amenities = [];
            foreach ($hotelIem->getAmenities()->getIterator() as $amenity) {
                /**
                 * @var $amenity HotelAmenities
                 */
                $amenities[] = $amenity->getGroup()->getName();

            }

            $image = $hotelIem->getImages()->get(0)?->getImage();
            $amenities = array_values(array_unique($amenities));
            $hotels[] = [
                'uri' => $hotelIem->getUri(),
                'title' => $hotelIem->getTitle(),
                'address' => $hotelIem->getAddress(),
                'star_rating' => $hotelIem->getStarRating(),
                'lng' => $hotelIem->getLongitude(),
                'lat' => $hotelIem->getLatitude(),
                'amenities' => $amenities,
                'image' => StringHelper::replaceWithinBracers($image ?? '', 'size', '1024x768'),
                'reviews' => [
                    'rating' => (float)$hotelIem->getClientRating(),
                    'reviews_quantity' => count($hotelIem->getReviews())
                ]
            ];

        }


        return $this->json([
            'success' => true,
            'data' => [
                'region_id' => $locationId,
                'total' => $totalHotels,
                'pages' => ceil($totalHotels / $perPage),
                'lng' => $currentLocation->getLongitude(),
                'lat' => $currentLocation->getLatitude(),
                'hotels' => $hotels,
            ],
        ]);
    }


    #[Route('/hotel/{uri}', name: 'app_hotels', methods: ["GET"])]
    public function hotelInfo($uri): JsonResponse
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
            /**
             * @var $room Room
             */
            $roomImages = [];
            foreach ($room->getImages()->getIterator() as $roomImage) {
                $roomImages[] = StringHelper::replaceWithinBracers($roomImage->getImage(), 'size', '1024x768');
            }
            $roomAmenities = [];
            foreach ($room->getAmenities()->getIterator() as $amenity) {
                /**
                 * @var $amenity RoomAmenities
                 */
                $roomAmenities[] = $amenity->getName();

            }
            $rooms[] = [
                'title' => $room->getTitle(),
                'images' => $roomImages,
                'amenities' => $roomAmenities,
                'ratehawk_room_group' => $room->getRoomGroup()
            ];

        }
        $hotelImages = [];
        foreach ($hotel?->getImages()->getIterator() as $hotelImage) {
            /**
             * @var $hotelImage HotelImage
             */
            $hotelImages[] = StringHelper::replaceWithinBracers($hotelImage->getImage(), 'size', '1024x768');

        }

        $hotelAmenities = [];
        foreach ($hotel?->getAmenities()->getIterator() as $hotelAmenity) {
            /**
             * @var $hotelAmenity HotelAmenities
             */
            $hotelAmenities[$hotelAmenity->getGroup()->getName()][] = $hotelAmenity->getName();

        }
        $hotelDescriptions = [];
        foreach ($hotel?->getDescriptions()->getIterator() as $hotelDescription) {
            /**
             * @var $hotelDescription HotelDescription
             */
            $hotelDescriptions[$hotelDescription->getDescriptionGroup()->getTitle()] = $hotelDescription->getText();
        }

        $reviews = [];


        //$hotel = $hotelRepository->findOneBy(['uri' => 'alaturca_house']);

        foreach ($hotel?->getReviews()->getIterator() as $review) {
            /**
             * @var $review Review
             */
            $reviews['rating'] = $hotel?->getClientRating();
            $reviews['detailed_ratings']['cleanness'] = $hotel?->getCleannessRating();
            $reviews['detailed_ratings']['location'] = $hotel?->getLocationRating();
            $reviews['detailed_ratings']['price'] = $hotel?->getPriceRating();
            $reviews['detailed_ratings']['services'] = $hotel?->getServicesRating();
            $reviews['detailed_ratings']['room'] = $hotel?->getRoomRating();
            $reviews['detailed_ratings']['meal'] = $hotel?->getMealRating();
            $reviews['detailed_ratings']['wifi'] = $hotel?->getWifiRating();
            $reviews['detailed_ratings']['hygiene'] = $hotel?->getHygieneRating();

            $images = [];
            foreach ($review->getImages()->getIterator() as $reviewImage) {
                /**
                 * @var $reviewImage ReviewImage
                 */
                $images[] = StringHelper::replaceWithinBracers($reviewImage->getImage(), 'size', '1024x768');
            }

            $reviews['reviews'][] = [
                'review_plus' => $review->getReviewPlus(),
                'review_minus' => $review->getReviewMinus(),
                'created' => $review->getCreatedAt(),
                'author' => $review->getAuthor(),
                'adults' => $review->getAdults(),
                'children' => $review->getChildren(),
                'room_name' => $review->getRoomName(),
                'nights' => $review->getNights(),
                'images' => $images,
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
