<?php

namespace App\Controller\Api;

use App\Entity\Payment;
use App\Repository\ClientRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/payments')]
class PaymentController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        ClientRepository $clientRepo,
        SubscriptionRepository $subscriptionRepo,
        OrderRepository $orderRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        $clientId = $data['client_id'] ?? null;
        $subscriptionId = $data['subscription_id'] ?? null;
        $orderId = $data['order_id'] ?? null;
        $paymentMethod = $data['payment_method'] ?? null;

        // ========================
        // ❌ VALIDATIONS
        // ========================
        if (!$clientId || !$paymentMethod) {
            return $this->json([
                "error" => "client_id et payment_method requis"
            ], 400);
        }

        if (!$subscriptionId && !$orderId) {
            return $this->json([
                "error" => "subscription_id ou order_id requis"
            ], 400);
        }

        // ❌ empêcher les deux en même temps
        if ($subscriptionId && $orderId) {
            return $this->json([
                "error" => "Un paiement ne peut pas être lié à une commande ET un abonnement"
            ], 400);
        }

        // ========================
        // 🔍 CLIENT
        // ========================
        $client = $clientRepo->find($clientId);

        if (!$client) {
            return $this->json([
                "error" => "Client introuvable"
            ], 404);
        }

        $payment = new Payment();
        $payment->setClient($client);
        $payment->setPaymentMethod($paymentMethod);
        $payment->setPaymentDate(new \DateTime());

        // ========================
        // 💳 CAS 1 : ABONNEMENT
        // ========================
        if ($subscriptionId) {

            $subscription = $subscriptionRepo->find($subscriptionId);

            if (!$subscription) {
                return $this->json([
                    "error" => "Abonnement introuvable"
                ], 404);
            }

            $payment->setSubscription($subscription);
            $payment->setAmount($subscription->getPrice());

            // 💡 référence auto
            $payment->setReference('SUB-' . uniqid());
        }

        // ========================
        // 🛒 CAS 2 : COMMANDE
        // ========================
        if ($orderId) {

            $order = $orderRepo->find($orderId);

            if (!$order) {
                return $this->json([
                    "error" => "Commande introuvable"
                ], 404);
            }

            // 🔥 empêcher double paiement
            if ($order->getStatus() === 'paid') {
                return $this->json([
                    "error" => "Cette commande est déjà payée"
                ], 400);
            }

            $payment->setOrders($order);
            $payment->setAmount($order->getTotalAmount());

            // 💡 référence auto
            $payment->setReference('ORD-' . uniqid());

            // ✅ mettre à jour statut commande
            $order->setStatus('paid');
        }

        // ========================
        // 💾 SAVE
        // ========================
        $em->persist($payment);
        $em->flush();

        return $this->json([
            "message" => "Paiement enregistré avec succès",
            "client" => $client->getFirstName() . ' ' . $client->getLastName(),
            "amount" => $payment->getAmount(),
            "method" => $paymentMethod,
            "reference" => $payment->getReference(),
            "date" => $payment->getPaymentDate()->format('Y-m-d H:i:s'),
            "type" => $subscriptionId ? "subscription" : "order"
        ], 201);
    }

    // ========================
    // 📄 LISTE DES PAIEMENTS
    // ========================
    #[Route('', methods: ['GET'])]
    public function list(
        \App\Repository\PaymentRepository $paymentRepo
    ): JsonResponse {

        $payments = $paymentRepo->findBy([], ['id' => 'DESC']);

        $data = [];

        foreach ($payments as $payment) {
            $data[] = [
                "id" => $payment->getId(),
                "client" => $payment->getClient()->getFirstName() . ' ' . $payment->getClient()->getLastName(),
                "amount" => $payment->getAmount(),
                "method" => $payment->getPaymentMethod(),
                "reference" => $payment->getReference(),
                "date" => $payment->getPaymentDate()->format('Y-m-d H:i:s'),
                "type" => $payment->getSubscription() ? "subscription" : "order"
            ];
        }

        return $this->json($data);
    }
}