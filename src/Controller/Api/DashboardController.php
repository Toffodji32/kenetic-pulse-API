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
        GymResolver $gymResolver,
        \Doctrine\DBAL\Connection $connection
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
        // 📈 TRAJECTOIRE DES REVENUS (6 derniers mois)
        // ========================
        $revenueTrend = $this->buildRevenueTrend($connection, $gym);

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
            ],
            "revenueTrend" => $revenueTrend
        ]);
    }

    private function buildRevenueTrend(\Doctrine\DBAL\Connection $connection, \App\Entity\Gym $gym): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = (new \DateTime('first day of this month'))->modify("-{$i} months")->setTime(0, 0);
            $monthEnd = (clone $monthStart)->modify('+1 month');
            $months[] = [
                'key' => $monthStart->format('Y-m'),
                'label' => $this->monthLabel($monthStart->format('n')),
                'start' => $monthStart,
                'end' => $monthEnd,
            ];
        }

        $rows = $connection->fetchAllAssociative(
            'SELECT EXTRACT(YEAR FROM payment_date) AS year, EXTRACT(MONTH FROM payment_date) AS month, SUM(amount) AS total
             FROM payment
             WHERE gym_id = :gym AND payment_date >= :firstMonth
             GROUP BY year, month',
            ['gym' => $gym->getId(), 'firstMonth' => $months[0]['start']->format('Y-m-d 00:00:00')]
        );

        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['year'] . '-' . str_pad((string) $row['month'], 2, '0', STR_PAD_LEFT)] = (float) $row['total'];
        }

        $result = [];
        foreach ($months as $month) {
            $result[] = [
                'month' => $month['label'],
                'current' => $totals[$month['key']] ?? 0.0,
                'previous' => $totals[date('Y-m', strtotime($month['key'] . '-01 -1 month'))] ?? 0.0,
            ];
        }

        return $result;
    }

    private function monthLabel(int $monthNumber): string
    {
        $labels = [1 => 'Janv.', 2 => 'Févr.', 3 => 'Mars', 4 => 'Avr.', 5 => 'Mai', 6 => 'Juin', 7 => 'Juil.', 8 => 'Août', 9 => 'Sept.', 10 => 'Oct.', 11 => 'Nov.', 12 => 'Déc.'];
        return $labels[$monthNumber] ?? (string) $monthNumber;
    }
}
