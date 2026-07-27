<?php

namespace App\EventListener;

use App\Entity\Gym;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsDoctrineListener(event: Events::prePersist)]
class GymEntityListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Only handle entities that have a setGym method
        if (!method_exists($entity, 'setGym') || !method_exists($entity, 'getGym')) {
            return;
        }

        // Skip if gym is already set
        if ($entity->getGym() !== null) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->isAuthenticated()) {
            return;
        }

        $user = $token->getUser();

        if (!$user instanceof \App\Entity\User) {
            return;
        }

        $gym = $user->getGym();

        if ($gym) {
            $entity->setGym($gym);
        }
    }
}
