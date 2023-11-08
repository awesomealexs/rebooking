<?php

namespace App\Controller;

use App\Entity\Hotel;
use App\Entity\HotelAmenities;
use App\Entity\HotelDescription;
use App\Entity\HotelImage;
use App\Entity\Location;
use App\Entity\Room;
use App\Entity\RoomAmenities;
use App\Helper\StringHelper;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class HotelsController extends AbstractController
{
    protected Logger $logger;

    protected EntityManager $entityManager;

    protected const HOTELS_PER_PAGE = 30;

    public function __construct()
    {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';


        $dotenv = new Dotenv(true);
        $dotenv
            ->usePutenv()
            ->bootEnv(dirname(__DIR__, 2) . '/.env');

        $params = [
            'driver' => getenv('DB_DRIVER'),
            'user' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
            'dbname' => getenv('DB_NAME'),
            'host' => getenv('DB_HOST'),
            'port' => getenv('DB_PORT'),
        ];

        $isDevMode = true;

        $this->entityManager = EntityManager::create($params, \Doctrine\ORM\Tools\Setup::createAttributeMetadataConfiguration([__DIR__ . "/Entity"], $isDevMode));
    }


    protected function checkAuth(Request $request)
    {
        $secret = getenv('API_AUTH_KEY');

        $signature = $request->headers->get('signature');
        $timestamp = $request->headers->get('timestamp');

        if((time() - (int) $timestamp) > 500){
            return false;
        }

        return md5($secret . $timestamp) === $signature;
    }

    #[Route('/hotels2loc/{location}', name: 'app_hotels_by_oc', methods: ["GET"])]
    public function hotelsByLocations(Request $request, $location): JsonResponse
    {
        if (!$this->checkAuth($request)) {
            return $this->json([
                'success' => false,
                'message' => 'auth failed'], 401);
        }

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
            'data' => $hotels
        ]);
    }


    #[Route('/hotel/{uri}', name: 'app_hotels', methods: ["GET"])]
    public function hotelInfo(Request $request, $uri): JsonResponse
    {
        if (!$this->checkAuth($request)) {
            return $this->json([
                'success' => false,
                'message' => 'auth failed'], 401);
        }

        $hotelRepository = $this->entityManager->getRepository(Hotel::class);
        $hotel = ($hotelRepository->findOneBy(['uri' => $uri]));

        $rooms = [];
        foreach ($hotel->getRooms()->getIterator() as $room) {
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
        foreach ($hotel->getImages()->getIterator() as $hotelImage) {
            if ($hotelImage instanceof HotelImage) {
                $hotelImages[] = StringHelper::replaceWithinBracers($hotelImage->getImage(), 'size', '1024x768');
            }
        }

        $hotelAmenities = [];
        foreach ($hotel->getAmenities()->getIterator() as $hotelAmenity) {
            if ($hotelAmenity instanceof HotelAmenities) {
                $hotelAmenities[$hotelAmenity->getGroup()->getName()][] = $hotelAmenity->getName();
            }
        }
        $hotelDescriptions = [];
        foreach ($hotel->getDescriptions()->getIterator() as $hotelDescription) {
            if ($hotelDescription instanceof HotelDescription) {
                $hotelDescriptions[$hotelDescription->getDescriptionGroup()->getTitle()] = $hotelDescription->getText();
            }
        }

        $hotelData = [
            'id' => $hotel->getUri(),
            'title' => $hotel->getTitle(),
            'address' => $hotel->getAddress(),
            'phone' => $hotel->getPhone(),
            'email' => $hotel->getEmail(),
            'check_in' => $hotel->getCheckIn(),
            'check_out' => $hotel->getCheckOut(),
            'star_rating' => $hotel->getStarRating(),
            'latitude' => $hotel->getLatitude(),
            'additional_information' => $hotel->getAdditionalInformation(),
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
