<?php

namespace App\Repository;

use App\Entity\Gym;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GymRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gym::class);
    }

    public function findOneBySlug(string $slug): ?Gym
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
