<?php

namespace App\Repository;

use App\Entity\Location;
use Doctrine\ORM\EntityManager;

class LocationRepository
{
    protected EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function insertRegion(array $regionData): void
    {
        if (empty($regionData)) {
            return;
        }

        $locationName = $regionData['name']['ru'] ?? $regionData['name']['en'];
        $locationRegion = $regionData['country_name']['ru'] ?? $regionData['country_name']['en'] ?? $locationName;
        $location = (new Location())
            ->setRateHawkId($regionData['id'])
            ->setLatitude($regionData['center']['latitude'])
            ->setLongitude($regionData['center']['longitude'])
            ->setCountryName($locationRegion)
            ->setCountryCode($regionData['country_code'] ?? '')
            ->setType($regionData['type'])
            ->setTitle($locationName);


        $this->entityManager->persist($location);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}

