<?php

namespace App\Repository;

use App\Entity\Gym;
use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
    public function findLatestByGym(Gym $gym, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.gym = :gym')
            ->setParameter('gym', $gym)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadByGym(Gym $gym): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.gym = :gym')
            ->andWhere('n.isRead = false')
            ->setParameter('gym', $gym)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllReadByGym(Gym $gym): int
    {
        return $this->createQueryBuilder('n')
            ->update(Notification::class, 'n')
            ->set('n.isRead', ':isRead')
            ->where('n.gym = :gym')
            ->andWhere('n.isRead = false')
            ->setParameter('gym', $gym)
            ->setParameter('isRead', true, \Doctrine\DBAL\Types\Types::BOOLEAN)
            ->getQuery()
            ->execute();
    }
}