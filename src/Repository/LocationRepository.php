<?php

namespace App\Repository;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\ORM\EntityManager;
use App\Entity\Location;

class LocationRepository
{

    protected const ROOT_BASE_OFFSET = 10000000000;

    protected const ROOT_PARENT_ID = 1;

    protected EntityManager $entityManager;

    protected array $rootCountries = [];

    protected int $baseMaxLocationId = 0;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function insertRegion(array $regionData, bool $flush = false): void
    {
        if (empty($regionData)) {
            return;
        }
        try {

            $locationName = $regionData['name']['ru'] ?? $regionData['name']['en'];
            $locationRegion = $regionData['country_name']['ru'] ?? $regionData['country_name']['en'] ?? $locationName;
            $location = (new Location())
                ->setRateHawkId($regionData['id'])
                ->setLatitude($regionData['center']['latitude'])
                ->setLongitude($regionData['center']['longitude'])
                ->setCountryName($locationRegion)
                ->setCountryCode($regionData['country_code']??'')
                ->setTitle($locationName);


            $this->entityManager->persist($location);
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/test', json_encode($regionData) . PHP_EOL, FILE_APPEND);

            var_dump($e->getMessage());
            die;

            var_dump($regionData);
            die;
        }
    }

    public function flush(){
        $this->entityManager->flush();
    }
}

