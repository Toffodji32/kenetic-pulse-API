<?php

namespace App\Repository;

use App\Entity\GymOwner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GymOwnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GymOwner::class);
    }

    public function findOneByEmail(string $email): ?GymOwner
    {
        return $this->findOneBy(['email' => $email]);
    }
}
