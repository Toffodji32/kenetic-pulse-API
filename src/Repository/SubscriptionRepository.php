<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    // ── 1 seul abonnement par client — le plus récent expiré ─────────────
    public function findExpired(\DateTime $today): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.client', 'c')
            ->where('s.endDate < :today')
            ->setParameter('today', $today)
            ->andWhere(
                's.id = (
                    SELECT MAX(s2.id) FROM App\Entity\Subscription s2
                    WHERE s2.client = s.client
                )'
            )
            ->orderBy('s.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ── 1 seul abonnement par client — le plus récent expirant bientôt ───
    public function findExpiringSoon(\DateTime $from, \DateTime $to): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.client', 'c')
            ->where('s.endDate BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->andWhere(
                's.id = (
                    SELECT MAX(s2.id) FROM App\Entity\Subscription s2
                    WHERE s2.client = s.client
                )'
            )
            ->orderBy('s.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
