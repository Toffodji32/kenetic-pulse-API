<?php

namespace App\Security;

use App\Entity\Gym;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class GymResolver
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {}

    public function getGym(): ?Gym
    {
        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->getUser()) {
            return null;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return $user->getGym();
    }

    public function getGymId(): ?int
    {
        $gym = $this->getGym();

        return $gym?->getId();
    }

    public function getGymSlug(): ?string
    {
        $gym = $this->getGym();

        return $gym?->getSlug();
    }
}
