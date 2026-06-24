<?php

namespace App\Controller\Api;

use App\Entity\Gym;
use App\Entity\GymSubscription;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\CheckinRepository;
use App\Repository\GymRepository;
use App\Repository\GymSubscriptionRepository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/super-admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class SuperAdminController extends AbstractController
{
    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(
        ClientRepository $clientRepo,
        UserRepository $userRepo,
        ProductRepository $productRepo,
        OrderRepository $orderRepo,
        PaymentRepository $paymentRepo,
        SubscriptionRepository $subRepo,
        CheckinRepository $checkinRepo,
        GymRepository $gymRepo,
        GymSubscriptionRepository $gymSubRepo,
    ): JsonResponse {
        $totalGyms = $gymRepo->count([]);

        // Gyms created this month
        $firstOfMonth = new \DateTime('first day of this month 00:00:00');
        $newGymsThisMonth = $gymRepo->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.createdAt >= :firstOfMonth')
            ->setParameter('firstOfMonth', $firstOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        // Gym subscriptions by status
        $trialGyms = $gymSubRepo->count(['status' => GymSubscription::STATUS_TRIAL]);
        $activeGyms = $gymSubRepo->count(['status' => GymSubscription::STATUS_ACTIVE]);
        $expiredGyms = $gymSubRepo->count(['status' => GymSubscription::STATUS_EXPIRED]);

        // Total revenue from gym subscriptions
        $subscriptionRevenue = $gymSubRepo->createQueryBuilder('gs')
            ->select('COALESCE(SUM(gs.amount), 0)')
            ->where('gs.status = :active')
            ->setParameter('active', GymSubscription::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        $totalClients = $clientRepo->count([]);
        $totalUsers = $userRepo->count([]);
        $totalProducts = $productRepo->count([]);

        $today = new \DateTime('today');

        $todayRevenue = $orderRepo->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->where('o.createdAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        $totalOrders = $orderRepo->count([]);

        $todayCheckins = $checkinRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.checkinTime >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'gyms' => [
                'total' => $totalGyms,
                'newThisMonth' => (int) $newGymsThisMonth,
                'trial' => $trialGyms,
                'active' => $activeGyms,
                'expired' => $expiredGyms,
            ],
            'clients' => ['total' => $totalClients],
            'users' => ['total' => $totalUsers],
            'products' => ['total' => $totalProducts],
            'orders' => [
                'total' => $totalOrders,
                'todayRevenue' => (float) $todayRevenue,
            ],
            'subscriptions' => [
                'revenue' => (float) $subscriptionRevenue,
            ],
            'checkins' => [
                'today' => (int) $todayCheckins,
            ],
        ]);
    }

    #[Route('/gyms', methods: ['GET'])]
    public function gyms(
        GymRepository $gymRepo,
        GymSubscriptionRepository $gymSubRepo,
        UserRepository $userRepo,
        ClientRepository $clientRepo,
    ): JsonResponse {
        $gyms = $gymRepo->findAll();
        $data = [];

        foreach ($gyms as $gym) {
            $sub = $gym->getGymSubscription();
            $userCount = $userRepo->count(['gym' => $gym]);
            $clientCount = $clientRepo->count(['gym' => $gym]);

            $data[] = [
                'id' => $gym->getId(),
                'name' => $gym->getName(),
                'slug' => $gym->getSlug(),
                'email' => $gym->getEmail(),
                'phone' => $gym->getPhone(),
                'address' => $gym->getAddress(),
                'logo' => $gym->getLogo(),
                'createdAt' => $gym->getCreatedAt()?->format('c'),
                'subscription' => $sub ? [
                    'status' => $sub->getStatus(),
                    'plan' => $sub->getPlan(),
                    'planType' => $sub->getPlanType(),
                    'amount' => $sub->getAmount(),
                    'trialEndsAt' => $sub->getTrialEndsAt()?->format('c'),
                    'endsAt' => $sub->getEndsAt()?->format('c'),
                    'createdAt' => $sub->getCreatedAt()?->format('c'),
                ] : null,
                'usersCount' => $userCount,
                'clientsCount' => $clientCount,
            ];
        }

        return $this->json($data);
    }

    #[Route('/gyms/{id}', methods: ['GET'])]
    public function gymDetail(
        int $id,
        GymRepository $gymRepo,
        UserRepository $userRepo,
        ClientRepository $clientRepo,
        ProductRepository $productRepo,
        OrderRepository $orderRepo,
    ): JsonResponse {
        $gym = $gymRepo->find($id);
        if (!$gym) {
            return $this->json(['error' => 'Gym not found'], 404);
        }

        $sub = $gym->getGymSubscription();
        $users = $userRepo->findBy(['gym' => $gym]);
        $userCount = count($users);
        $clientCount = $clientRepo->count(['gym' => $gym]);
        $productCount = $productRepo->count(['gym' => $gym]);

        $revenue = $orderRepo->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->where('o.gym = :gym')
            ->setParameter('gym', $gym)
            ->getQuery()
            ->getSingleScalarResult();

        $userList = array_map(fn($u) => [
            'id' => $u->getId(),
            'name' => $u->getName(),
            'email' => $u->getEmail(),
            'roles' => $u->getRoles(),
            'createdAt' => $u->getCreatedAt()?->format('c'),
        ], $users);

        return $this->json([
            'id' => $gym->getId(),
            'name' => $gym->getName(),
            'slug' => $gym->getSlug(),
            'email' => $gym->getEmail(),
            'phone' => $gym->getPhone(),
            'address' => $gym->getAddress(),
            'logo' => $gym->getLogo(),
            'description' => $gym->getDescription(),
            'createdAt' => $gym->getCreatedAt()?->format('c'),
            'subscription' => $sub ? [
                'id' => $sub->getId(),
                'status' => $sub->getStatus(),
                'plan' => $sub->getPlan(),
                'planType' => $sub->getPlanType(),
                'amount' => $sub->getAmount(),
                'trialEndsAt' => $sub->getTrialEndsAt()?->format('c'),
                'startsAt' => $sub->getStartsAt()?->format('c'),
                'endsAt' => $sub->getEndsAt()?->format('c'),
                'createdAt' => $sub->getCreatedAt()?->format('c'),
                'updatedAt' => $sub->getUpdatedAt()?->format('c'),
                'fedapayTransactionId' => $sub->getFedapayTransactionId(),
            ] : null,
            'usersCount' => $userCount,
            'clientsCount' => $clientCount,
            'productsCount' => $productCount,
            'totalRevenue' => (float) $revenue,
            'users' => $userList,
        ]);
    }

    #[Route('/gyms/{id}/toggle-status', methods: ['POST'])]
    public function toggleGymStatus(
        int $id,
        Request $request,
        GymRepository $gymRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $gym = $gymRepo->find($id);
        if (!$gym) {
            return $this->json(['error' => 'Gym not found'], 404);
        }

        $sub = $gym->getGymSubscription();
        if (!$sub) {
            return $this->json(['error' => 'No subscription found for this gym'], 404);
        }

        $body = json_decode($request->getContent(), true);
        $newStatus = $body['status'] ?? null;

        $allowed = [
            GymSubscription::STATUS_TRIAL,
            GymSubscription::STATUS_ACTIVE,
            GymSubscription::STATUS_EXPIRED,
            GymSubscription::STATUS_CANCELLED,
        ];

        if (!$newStatus || !in_array($newStatus, $allowed, true)) {
            return $this->json(['error' => 'Invalid status. Allowed: ' . implode(', ', $allowed)], 400);
        }

        $sub->setStatus($newStatus);
        $sub->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json([
            'id' => $gym->getId(),
            'name' => $gym->getName(),
            'subscription' => [
                'status' => $sub->getStatus(),
                'planType' => $sub->getPlanType(),
                'updatedAt' => $sub->getUpdatedAt()?->format('c'),
            ],
        ]);
    }

    #[Route('/subscriptions', methods: ['GET'])]
    public function subscriptions(
        GymSubscriptionRepository $gymSubRepo,
    ): JsonResponse {
        $all = $gymSubRepo->findAll();
        $data = array_map(fn(GymSubscription $gs) => [
            'id' => $gs->getId(),
            'gym' => $gs->getGym() ? [
                'id' => $gs->getGym()->getId(),
                'name' => $gs->getGym()->getName(),
                'slug' => $gs->getGym()->getSlug(),
                'email' => $gs->getGym()->getEmail(),
            ] : null,
            'status' => $gs->getStatus(),
            'plan' => $gs->getPlan(),
            'planType' => $gs->getPlanType(),
            'amount' => $gs->getAmount(),
            'trialEndsAt' => $gs->getTrialEndsAt()?->format('c'),
            'startsAt' => $gs->getStartsAt()?->format('c'),
            'endsAt' => $gs->getEndsAt()?->format('c'),
            'createdAt' => $gs->getCreatedAt()?->format('c'),
            'updatedAt' => $gs->getUpdatedAt()?->format('c'),
            'fedapayTransactionId' => $gs->getFedapayTransactionId(),
        ], $all);

        return $this->json($data);
    }
}
