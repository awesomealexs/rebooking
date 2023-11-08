<?php

namespace App\Repository;

use App\Entity\Hotel;
use App\Entity\HotelAmenities;
use App\Entity\HotelAmenitiesGroups;
use App\Entity\HotelDescription;
use App\Entity\HotelDescriptionGroup;
use App\Entity\HotelImage;
use App\Entity\Location;
use App\Entity\Room;
use App\Entity\RoomAmenities;
use App\Entity\RoomImage;
use Doctrine\ORM\EntityManagerInterface;

class HotelRepository
{
    protected EntityManagerInterface $entityManager;

    protected array $amenities = [];

    protected array $roomAmenities;

    protected array $descriptionGroups;

    protected array $locations;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->initEntities();
    }

    public function initEntities(): void
    {
        $this->initAmenities();
        $this->initDescriptionGroups();
        $this->initRoomAmenities();
        $this->initLocations();
    }

    protected function initLocations(): void
    {
        $locations = $this->entityManager->getRepository(Location::class)->findAll();
        foreach ($locations as $location) {
            $this->locations[$location->getRateHawkId()] = $location;
        }
    }

    protected function initRoomAmenities(): void
    {
        $items = $this->entityManager->getRepository(RoomAmenities::class)->findAll();
        foreach ($items as $item) {
            $this->roomAmenities[$item->getName()] = $item;
        }
    }

    protected function initDescriptionGroups(): void
    {
        $items = $this->entityManager->getRepository(HotelDescriptionGroup::class)->findAll();
        foreach ($items as $item) {
            $this->descriptionGroups[$item->getTitle()] = $item;
        }
    }

    protected function initAmenities(): void
    {
        $groups = $this->entityManager->getRepository(HotelAmenitiesGroups::class);
        foreach ($groups->findAll() as $item) {
            $this->amenities[$item->getName()]['entity'] = $item;

            foreach ($item->getAmenities()->getIterator() as $amenityItem) {
                $this->amenities[$item->getName()]['items'][$amenityItem->getName()] = $amenityItem;
            }
        }
    }

    public function saveAmenities(array $amenitiesGroups)
    {
        $changes = false;
        foreach ($amenitiesGroups as $amenitiesGroup) {
            if (isset($this->amenities[$amenitiesGroup['group_name']])) {
                $amenitiesGroupItem = $this->amenities[$amenitiesGroup['group_name']]['entity'];
            } else {
                $amenitiesGroupItem = (new HotelAmenitiesGroups())
                    ->setName($amenitiesGroup['group_name']);
                $changes = true;
                $this->amenities[$amenitiesGroup['group_name']]['entity'] = &$amenitiesGroupItem;
                $this->amenities[$amenitiesGroup['group_name']]['items'] = [];
            }

            foreach ($amenitiesGroup['amenities'] as $amenityItem) {
                if (!array_key_exists($amenityItem, $this->amenities[$amenitiesGroup['group_name']]['items'])) {
                    $amenity = (new HotelAmenities())
                        ->setName($amenityItem)
                        ->setGroup($amenitiesGroupItem);
                    $amenitiesGroupItem->setAmenities($amenity);
                    $changes = true;
                    $this->amenities[$amenitiesGroup['group_name']]['items'][$amenityItem] = &$amenity;
                    $this->entityManager->persist($amenitiesGroupItem);
                }
            }
        }
        if ($changes) {
            $this->entityManager->flush();
        }
    }

    public function flush()
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    protected function removeSpecialChars(string $string): string
    {
        return preg_replace('/[^а-яА-ЯёЁa-zA-Z0-9\-\,[:space:]]/u', '', $string);
    }

    public function insertHotel(array $hotelData): void
    {
        $hotel = (new Hotel())
            ->setPhone($hotelData['phone'] ?? '')
            ->setTitle($this->removeSpecialChars($hotelData['name']))
            ->setUri($hotelData['id'])
            ->setEmail($hotelData['email'] ?? '')
            ->setLocation(@$this->locations[$hotelData['region']['id']] ?? null)
            ->setStarRating($hotelData['star_rating'] ?? 0)
            ->setCheckIn($hotelData['check_in_time'] ?? '')
            ->setCheckOut($hotelData['check_out_time'] ?? '')
            ->setAddress($this->removeSpecialChars($hotelData['address']) ?? '')
            ->setLongitude($hotelData['longitude'] ?? '')
            ->setLatitude($hotelData['latitude'] ?? '')
            ->setAdditionalInformation('');

        foreach ($hotelData['amenity_groups'] as $amenityGroup) {
            foreach ($amenityGroup['amenities'] as $item) {
                $hotel->addAmenities($this->amenities[$amenityGroup['group_name']]['items'][$item]);
            }
        }

        foreach ($hotelData['images'] as $idx => $imageUrl) {
            $hotelImage = (new HotelImage())
                ->setImageSort($idx + 1)
                ->setImage($imageUrl)
                ->setAlt('');
            $hotel->addImage($hotelImage);
        }

        foreach ($hotelData['description_struct'] as $descriptionStruct) {
            if (isset($this->descriptionGroups[$descriptionStruct['title']])) {
                $descriptionStructItem = $this->descriptionGroups[$descriptionStruct['title']];
            } else {
                $descriptionStructItem = (new HotelDescriptionGroup())
                    ->setTitle($descriptionStruct['title'] ?? '')
                    ->setIcon('');
                $this->descriptionGroups[$descriptionStruct['title']] = $descriptionStructItem;
            }

            $descriptionText = implode("\n", $descriptionStruct['paragraphs']);

            $hotelDescription = (new HotelDescription())
                ->setDescriptionGroup($descriptionStructItem)
                ->setHotel($hotel)
                ->setText($descriptionText);

            $hotel->setDescriptions($hotelDescription);
        }

        foreach ($hotelData['room_groups'] as $roomData) {
            $room = (new Room())
                ->setTitle($roomData['name'])
                ->setRoomGroup($roomData['room_group_id'])
                ->setDescription('')
                ->setUri($this->translit($roomData['name']));

            foreach ($roomData['room_amenities'] as $item) {
                if (isset($this->roomAmenities[$item])) {
                    $roomAmenitiesItem = $this->roomAmenities[$item];
                } else {
                    $roomAmenitiesItem = (new RoomAmenities())
                        ->setName($item)
                        ->setIcon('');
                    $this->roomAmenities[$item] = $roomAmenitiesItem;
                }

                $room->addAmenities($roomAmenitiesItem);
            }


            foreach ($roomData['images'] as $idx => $imageUrl) {
                $roomImage = (new RoomImage())
                    ->setImage($imageUrl)
                    ->setAlt('')
                    ->setImageSort($idx + 1);

                $room->addImage($roomImage);
            }
            $hotel->addRoom($room);
        }


        $this->entityManager->persist($hotel);
    }

    protected function translit(string $value): string
    {
        $converter = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        );

        $value = mb_strtolower($value);
        $value = strtr($value, $converter);
        $value = mb_ereg_replace('[^-0-9a-z]', '-', $value);
        $value = mb_ereg_replace('[-]+', '-', $value);
        $value = trim($value, '-');

        return $value;
    }
}

