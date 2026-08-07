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

        // Routes toujours accessibles même en cas d'expiration (login, paiement, super-admin...)
        $allowedPaths = [
            '/api/login',
            '/api/gym/register',
            '/api/gym/subscription',
            '/api/shop',
            '/api/super-admin',
        ];

        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->getUser()) {
            return;
        }

        $user = $token->getUser();

        if (!$user instanceof \App\Entity\User) {
            return;
        }

        // Super admin bypass
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return;
        }

        // Ne bloquer que les administrateurs et réceptionnistes, pas les clients boutique
        $roles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $roles, true) && !in_array('ROLE_USER', $roles, true)) {
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

        // Auto-expire si le trial est dépassé ou si l'abonnement payé est échu
        $now = new \DateTime();
        if ($subscription->getStatus() === \App\Entity\GymSubscription::STATUS_TRIAL
            && $subscription->getTrialEndsAt() < $now) {
            $subscription->setStatus(\App\Entity\GymSubscription::STATUS_EXPIRED);
            $subscription->setUpdatedAt($now);
            $this->em->flush();
        } elseif ($subscription->getStatus() === \App\Entity\GymSubscription::STATUS_ACTIVE
            && $subscription->getEndsAt() !== null
            && $subscription->getEndsAt() < $now) {
            $subscription->setStatus(\App\Entity\GymSubscription::STATUS_EXPIRED);
            $subscription->setUpdatedAt($now);
            $this->em->flush();
        }

        // Blocage : l'utilisateur peut toujours accéder à certaines routes
        foreach ($allowedPaths as $allowed) {
            if (str_starts_with($path, $allowed)) {
                return;
            }
        }

        if ($subscription->getStatus() === \App\Entity\GymSubscription::STATUS_EXPIRED) {
            $event->setResponse(new JsonResponse([
                'error' => 'Votre abonnement a expiré. Veuillez effectuer le paiement pour continuer à utiliser le service.',
                'code' => 'SUBSCRIPTION_EXPIRED',
            ], 402));
        }
    }
}
