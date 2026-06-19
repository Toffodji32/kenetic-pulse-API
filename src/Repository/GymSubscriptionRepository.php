<?php

namespace App\Repository;

use App\Entity\GymSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GymSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GymSubscription::class);
    }

    public function findAllExpired(): array
    {
        $now = new \DateTime();
        return $this->createQueryBuilder('gs')
            ->where('gs.status = :trial AND gs.trialEndsAt < :now')
            ->orWhere('gs.status = :active AND gs.endsAt < :now')
            ->setParameter('trial', GymSubscription::STATUS_TRIAL)
            ->setParameter('active', GymSubscription::STATUS_ACTIVE)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    public function findAllExpiringSoon(int $days = 3): array
    {
        $threshold = (new \DateTime())->modify("+{$days} days");
        return $this->createQueryBuilder('gs')
            ->where('gs.status = :trial AND gs.trialEndsAt <= :threshold AND gs.trialEndsAt > :now')
            ->orWhere('gs.status = :active AND gs.endsAt <= :threshold AND gs.endsAt > :now')
            ->setParameter('trial', GymSubscription::STATUS_TRIAL)
            ->setParameter('active', GymSubscription::STATUS_ACTIVE)
            ->setParameter('threshold', $threshold)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }
}
