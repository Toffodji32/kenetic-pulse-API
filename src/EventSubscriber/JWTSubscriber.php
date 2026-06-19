<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: Events::JWT_CREATED, method: 'onJWTCreated')]
class JWTSubscriber
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $gym = $user->getGym();

        if ($gym) {
            $payload['gym_id'] = $gym->getId();
            $payload['gym_slug'] = $gym->getSlug();
        }

        $event->setData($payload);
    }
}
