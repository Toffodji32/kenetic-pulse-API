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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dashboard')]
class DashboardController extends AbstractController
{
    #[Route('/', methods: ['GET'])]
    public function index(
        ClientRepository $clientRepo,
        UserRepository $userRepo,
        ProductRepository $productRepo,
        OrderRepository $orderRepo,
        PaymentRepository $paymentRepo,
        SubscriptionRepository $subRepo,
        CheckinRepository $checkinRepo
    ): JsonResponse {

        // ========================
        // 👥 CLIENTS & USERS
        // ========================
        $totalClients = $clientRepo->count([]);
        $totalUsers = $userRepo->count([]);

        // ========================
        // 📦 PRODUITS
        // ========================
        $totalProducts = $productRepo->count([]);

        // produits en rupture
        $outOfStockProducts = $productRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.quantity = 0')
            ->getQuery()
            ->getSingleScalarResult();

        // ========================
        // 🛒 COMMANDES
        // ========================
        $totalOrders = $orderRepo->count([]);

        // chiffre d’affaire total (orders)
        $totalRevenue = $orderRepo->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // chiffre du jour
        $today = new \DateTime('today');

        $todayRevenue = $orderRepo->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->where('o.createdAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // ========================
        // 💳 PAIEMENTS
        // ========================
        $totalPayments = $paymentRepo->count([]);

        // ========================
        // 🏋️ ABONNEMENTS
        // ========================
        $activeSubscriptions = $subRepo->count(['status' => 'actif']);
        $expiredSubscriptions = $subRepo->count(['status' => 'expire']);

        // ========================
        // 🚪 CHECKINS
        // ========================
        $todayCheckins = $checkinRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.checkinTime >= :today')
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