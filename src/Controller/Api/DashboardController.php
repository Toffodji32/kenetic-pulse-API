<?php

namespace App\Controller\Api;

use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\OrderItemRepository;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\CheckinRepository;
use App\Security\GymResolver;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dashboard')]
class DashboardController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(
        ClientRepository $clientRepo,
        UserRepository $userRepo,
        ProductRepository $productRepo,
        OrderRepository $orderRepo,
        OrderItemRepository $orderItemRepo,
        PaymentRepository $paymentRepo,
        SubscriptionRepository $subRepo,
        CheckinRepository $checkinRepo,
        GymResolver $gymResolver
    ): JsonResponse {

        // ========================
        // 🏠 GYM DU CONNECTÉ (isolation multi-tenant)
        // ========================
        $gym = $gymResolver->getGym();

        if (!$gym) {
            return $this->json([
                'clients' => ['total' => 0],
                'users' => ['total' => 0],
                'products' => ['total' => 0, 'outOfStock' => 0],
                'orders' => ['total' => 0, 'totalRevenue' => 0, 'todayRevenue' => 0],
                'payments' => ['total' => 0],
                'subscriptions' => ['active' => 0, 'expired' => 0],
                'checkins' => ['today' => 0],
            ]);
        }

        // ========================
        // 👥 CLIENTS & USERS
        // ========================
        $totalClients = $clientRepo->count(['gym' => $gym]);
        $totalUsers = $userRepo->count(['gym' => $gym]);

        // ========================
        // 📦 PRODUITS
        // ========================
        $totalProducts = $productRepo->count(['gym' => $gym]);

        // produits en rupture
        $outOfStockProducts = $productRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.quantity = 0')
            ->andWhere('p.gym = :gym')
            ->setParameter('gym', $gym)
            ->getQuery()
            ->getSingleScalarResult();

        // ========================
        // 🛒 COMMANDES
        // ========================
        $totalOrders = $orderRepo->count(['gym' => $gym]);

        // chiffre d’affaire total (orders de la salle)
        $totalRevenue = $orderRepo->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->where('o.gym = :gym')
            ->setParameter('gym', $gym)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // chiffre du jour
        $today = new \DateTime('today');

        $todayRevenue = $orderRepo->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->where('o.gym = :gym')
            ->andWhere('o.createdAt >= :today')
            ->setParameter('gym', $gym)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // ========================
        // 💳 PAIEMENTS
        // ========================
        $totalPayments = $paymentRepo->count(['gym' => $gym]);

        // ========================
        // 🏋️ ABONNEMENTS
        // ========================
        $activeSubscriptions = $subRepo->count(['gym' => $gym, 'status' => 'actif']);
        $expiredSubscriptions = $subRepo->count(['gym' => $gym, 'status' => 'expire']);

        // ========================
        // 🚪 CHECKINS
        // ========================
        $todayCheckins = $checkinRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.checkinTime >= :today')
            ->andWhere('c.gym = :gym')
            ->setParameter('gym', $gym)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        // ========================
        // 📊 RESPONSE
        // ========================
        return $this->json([
            "clients" => [
                "total" => $totalClients
            ],
            "users" => [
                "total" => $totalUsers
            ],
            "products" => [
                "total" => $totalProducts,
                "outOfStock" => (int) $outOfStockProducts
            ],
            "orders" => [
                "total" => $totalOrders,
                "totalRevenue" => (float) $totalRevenue,
                "todayRevenue" => (float) $todayRevenue
            ],
            "payments" => [
                "total" => $totalPayments
            ],
            "subscriptions" => [
                "active" => $activeSubscriptions,
                "expired" => $expiredSubscriptions
            ],
            "checkins" => [
                "today" => (int) $todayCheckins
            ]
        ]);
    }
}
