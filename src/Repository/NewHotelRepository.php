<?php

namespace App\Repository;

class NewHotelRepository
{
    protected \mysqli $db;

    protected array $stmt;

    protected array $locations;

    protected array $amenities;

    protected array $roomAmenities = [];

    protected array $descriptionGroups = [];

    public function __construct()
    {
        $this->db = new \mysqli(
            getenv('DB_HOST'),
            getenv('DB_USER'),
            getenv('DB_PASSWORD'),
            getenv('DB_NAME'),
            getenv('DB_PORT')
        );
        $this->initLocations();
        $this->initHotelAmenities();
        $this->initRoomAmenities();
        $this->initDescriptionGroups();

        $this->db->query("DELETE FROM hotels WHERE uri = 'testid'");


        $this->stmt['hotel'] = $this->db->prepare("INSERT INTO hotels (location_id, uri, title, latitude, longitude, phone, email, check_in, check_out, star_rating, address, additional_information)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $this->stmt['hotelImages'] = $this->db->prepare("INSERT INTO hotel_images (hotel_id, image_sort, image, alt) VALUES (?,?,?, '')");
        $this->stmt['descriptionGroup'] = $this->db->prepare("INSERT INTO hotel_description_groups (title, icon) VALUES (?,'')");
        $this->stmt['hotelDescription'] = $this->db->prepare("INSERT INTO hotel_description (hotel_id, description_group_id, text) VALUES (?,?,?)");
        $this->stmt['h2a'] = $this->db->prepare("INSERT INTO hotels_amenities (hotel_id, hotel_amenities_id) VALUES (?,?)");
        $this->stmt['rooms'] = $this->db->prepare("INSERT INTO rooms (hotel_id, title, description, uri, ratehawk_room_group) VALUES (?,?,?,?,?)");
        $this->stmt['roomImages'] = $this->db->prepare("INSERT INTO room_images (room_id, image_sort, image, alt) VALUES (?,?,?,'')");
        $this->stmt['roomAmenities'] = $this->db->prepare("INSERT INTO room_amenities (name, icon) VALUES (?,'')");
        $this->stmt['r2a'] = $this->db->prepare("INSERT INTO rooms_amenities (room_id, room_amenities_id) VALUES (?,?)");

    }

    protected function initLocations(): void
    {
        $q = $this->db->query("SELECT id, rate_hawk_id FROM locations");
        while ($row = $q->fetch_assoc()) {
            $this->locations[$row['rate_hawk_id']] = $row['id'];
        }
    }

    protected function initDescriptionGroups(): void
    {
        $q = $this->db->query("SELECT id, title FROM hotel_description_groups");
        while ($row = $q->fetch_assoc()) {
            $this->descriptionGroups[$row['title']] = $row['id'];
        }
    }

    protected function initRoomAmenities(): void
    {
        $q = $this->db->query("SELECT id, name FROM room_amenities");
        while ($row = $q->fetch_assoc()) {
            $this->roomAmenities[$row['name']] = $row['id'];
        }
    }

    protected function initHotelAmenities(): void
    {
        $q = $this->db->query("SELECT a.id, a.name, (SELECT g.name FROM hotel_amenities_groups as g WHERE g.id = a.group_id) as groupName FROM hotel_amenities as a ");

        while ($row = $q->fetch_assoc()) {
            $this->amenities[$row['groupName']][$row['name']] = $row['id'];
        }
    }

    protected function removeSpecialChars(string $string): string
    {
        return preg_replace('/[^а-яА-ЯёЁa-zA-Z0-9\-\,[:space:]]/u', '', $string);
    }

    public function insertHotel(array $hotelData = [])
    {
        if ($this->stmt['hotel'] instanceof \mysqli_stmt) {
            $locationId = $this->locations[$hotelData['region']['id']];
            $phone = $hotelData['phone'] ?? '';
            $email = $hotelData['email'] ?? '';
            $address = $this->removeSpecialChars($hotelData['address']) ?? '';
            $longitude = $hotelData['longitude'] ?? '';
            $latitude = $hotelData['latitude'] ?? '';
            $checkInTime = $hotelData['check_in_time'] ?? '';
            $checkOutTime = $hotelData['check_out_time'] ?? '';
            $additionalInfo = '';
            $this->stmt['hotel']->bind_param(
                'isssssssssss',
                $locationId,
                $hotelData['id'],
                $hotelData['name'],
                $latitude,
                $longitude,
                $phone,
                $email,
                $checkInTime,
                $checkOutTime,
                $hotelData['star_rating'],
                $address,
                $additionalInfo
            );
            $this->stmt['hotel']->execute();

            $hotelId = $this->stmt['hotel']->insert_id;
        }

        if ($this->stmt['hotelImages'] instanceof \mysqli_stmt) {
            foreach ($hotelData['images'] as $idx => $image) {
                $imageSort = $idx + 1;
                $this->stmt['hotelImages']->bind_param(
                    'iis',
                    $hotelId,
                    $imageSort,
                    $image
                );
                $this->stmt['hotelImages']->execute();
            }
        }

        if($this->stmt['hotelDescription'] instanceof \mysqli_stmt){
            foreach ($hotelData['description_struct'] as $descriptionItem) {
                $descriptionGroupId = $this->getDescriptionGroupId($descriptionItem['title']);
                $descriptionText = implode("\n", $descriptionItem['paragraphs']);

                $this->stmt['hotelDescription']->bind_param(
                    'iis',
                    $hotelId,
                    $descriptionGroupId,
                    $descriptionText
                );
                $this->stmt['hotelDescription']->execute();
            }
        }



        if ($this->stmt['h2a'] instanceof \mysqli_stmt) {
            foreach ($hotelData['amenity_groups'] as $amenityGroup) {
                foreach ($amenityGroup['amenities'] as $amenity) {
                    $amenityGroupId = $this->amenities[$amenityGroup['group_name']][$amenity];
                    $this->stmt['h2a']->bind_param('ii', $hotelId, $amenityGroupId);
                    $this->stmt['h2a']->execute();
                }
            }
        }

        if ($this->stmt['rooms'] instanceof \mysqli_stmt) {
            foreach ($hotelData['room_groups'] as $room) {
                $description = '';
                $roomUri = $this->translit($room['name']);
                $this->stmt['rooms']->bind_param(
                    'isssi',
                    $hotelId,
                    $room['name'],
                    $description,
                    $roomUri,
                    $room['room_group_id']
                );
                $this->stmt['rooms']->execute();

                $roomId = $this->stmt['rooms']->insert_id;
                if ($this->stmt['roomImages'] instanceof \mysqli_stmt) {
                    foreach ($room['images'] as $idx => $image) {
                        $imageSort = $idx + 1;
                        $this->stmt['roomImages']->bind_param(
                            'iis',
                            $roomId,
                            $imageSort,
                            $image
                        );
                        $this->stmt['roomImages']->execute();
                    }
                }
                if ($this->stmt['r2a'] instanceof \mysqli_stmt) {
                    foreach ($room['room_amenities'] as $amenity) {
                        $amenityId = $this->getRoomAmenitiesId($amenity);
                        $this->stmt['r2a']->bind_param(
                            'ii',
                            $roomId,
                            $amenityId
                        );
                        $this->stmt['r2a']->execute();
                    }
                }
            }
        }


    }

    protected function getDescriptionGroupId(string $title): int
    {

        if (!isset($this->descriptionGroups[$title])) {
            if($this->stmt['descriptionGroup'] instanceof \mysqli_stmt){
                $this->stmt['descriptionGroup']->bind_param(
                    's',
                    $title
                );
                $this->stmt['descriptionGroup']->execute();
                return $this->stmt['descriptionGroup']->insert_id;
            }
        }

        return $this->descriptionGroups[$title];
    }

    protected function getRoomAmenitiesId(string $amenityName): int
    {
        if (!isset($this->roomAmenities[$amenityName])) {
            if ($this->stmt['roomAmenities'] instanceof \mysqli_stmt) {
                $this->stmt['roomAmenities']->bind_param(
                    's',
                    $amenityName
                );
                $this->stmt['roomAmenities']->execute();
                return $this->stmt['roomAmenities']->insert_id;
            }
        }

        return $this->roomAmenities[$amenityName];
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
