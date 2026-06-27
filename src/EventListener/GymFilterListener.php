<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 10)]
class GymFilterListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private EntityManagerInterface $em,
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->getUser()) {
            return;
        }

        $user = $token->getUser();

        if (!$user instanceof \App\Entity\User) {
            return;
        }

        // Super admin sees all data — no gym filter
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            return;
        }

        $gym = $user->getGym();

        if (!$gym) {
            return;
        }

        $filters = $this->em->getFilters();
        if ($filters->has('gym_filter') && !$filters->isEnabled('gym_filter')) {
            $filters->enable('gym_filter');
        }

        if ($filters->isEnabled('gym_filter')) {
            $filters->getFilter('gym_filter')->setParameter('gym_id', (string) $gym->getId());
        }
    }
}
