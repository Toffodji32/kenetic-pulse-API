<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
class GymSubscriptionCheckListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private EntityManagerInterface $em,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Skip OPTIONS (CORS preflight)
        if ($request->getMethod() === 'OPTIONS') {
            return;
        }

        $path = $request->getPathInfo();

        // Always allow these routes
        $allowedPaths = [
            '/api/login',
            '/api/gym/register',
            '/api/gym/subscription',
        ];

        foreach ($allowedPaths as $allowed) {
            if (str_starts_with($path, $allowed)) {
                return;
            }
        }

        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->getUser()) {
            return;
        }

        $user = $token->getUser();

        if (!$user instanceof \App\Entity\User) {
            return;
        }

        $gym = $user->getGym();

        if (!$gym) {
            return;
        }

        $subscription = $gym->getGymSubscription();

        if (!$subscription) {
            return;
        }

        // Auto-expire if trial expired
        if ($subscription->getStatus() === \App\Entity\GymSubscription::STATUS_TRIAL
            && $subscription->getTrialEndsAt() < new \DateTime()) {
            $subscription->setStatus(\App\Entity\GymSubscription::STATUS_EXPIRED);
            $subscription->setUpdatedAt(new \DateTime());
            $this->em->flush();
        }

        if ($subscription->getStatus() === \App\Entity\GymSubscription::STATUS_EXPIRED) {
            $event->setResponse(new JsonResponse([
                'error' => 'Votre abonnement a expiré. Veuillez effectuer le paiement pour continuer à utiliser le service.',
                'code' => 'SUBSCRIPTION_EXPIRED',
            ], 402));
        }
    }
}
