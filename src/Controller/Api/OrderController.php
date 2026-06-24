<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Repository\ClientRepository;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders')]
class OrderController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        ClientRepository $clientRepo,
        ProductRepository $productRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $data     = json_decode($request->getContent(), true);
        $clientId = $data['client_id'] ?? null;
        $items    = $data['items']     ?? [];

        if (!$clientId || empty($items)) {
            return $this->json(["error" => "client_id et items requis"], 400);
        }

        $client = $clientRepo->find($clientId);
        if (!$client) {
            return $this->json(["error" => "Client introuvable"], 404);
        }

        $order = new Order();
        $order->setClient($client);
        $order->setCreatedAt(new \DateTime());
        $order->setStatus('pending');

        $total = 0;

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity  = $item['quantity']   ?? 0;

            if (!$productId || $quantity <= 0) {
                return $this->json(["error" => "product_id et quantity (>0) requis"], 400);
            }

            $product = $productRepo->find($productId);
            if (!$product) {
                return $this->json(["error" => "Produit ID $productId introuvable"], 404);
            }

            if ($product->getQuantity() < $quantity) {
                return $this->json(["error" => "Stock insuffisant pour " . $product->getName()], 400);
            }

            $price    = $product->getPrice();
            $subtotal = $price * $quantity;

            $product->setQuantity($product->getQuantity() - $quantity);

            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($quantity);
            $orderItem->setPrice($price);
            $orderItem->setOrders($order);

            $em->persist($orderItem);
            $total += $subtotal;
        }

        $order->setTotalAmount($total);
        $em->persist($order);
        $em->flush();

        return $this->json([
            "message"  => "Commande créée avec succès",
            "order_id" => $order->getId(),
            "client"   => $client->getFirstName() . ' ' . $client->getLastName(),
            "total"    => $total,
            "status"   => $order->getStatus()
        ], 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(OrderRepository $orderRepo): JsonResponse
    {
        $orders = $orderRepo->findBy([], ['id' => 'DESC']);
        $data   = [];

        foreach ($orders as $order) {
            $items = [];
            foreach ($order->getOrderItems() as $item) {
                $items[] = [
                    "product"  => $item->getProduct()->getName(),
                    "quantity" => $item->getQuantity(),
                    "price"    => $item->getPrice()
                ];
            }
            $data[] = [
                "id"                     => $order->getId(),
                "client"                 => $order->getClient()->getFirstName() . ' ' . $order->getClient()->getLastName(),
                "total"                  => $order->getTotalAmount(),
                "status"                 => $order->getStatus(),
                "date"                   => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                "items"                  => $items,
                // ← NOUVEAU
                "fedapay_transaction_id" => $order->getFedapayTransactionId(),
                "delivery_type"    => $order->getDeliveryType(),
                "delivery_address" => $order->getDeliveryAddress(),
                "delivery_status"  => $order->getDeliveryStatus(),
                "fedapay_transaction_id" => $order->getFedapayTransactionId(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Order $order): JsonResponse
    {
        $items = [];
        foreach ($order->getOrderItems() as $item) {
            $items[] = [
                "product"  => $item->getProduct()->getName(),
                "quantity" => $item->getQuantity(),
                "price"    => $item->getPrice()
            ];
        }

        return $this->json([
            "id"                     => $order->getId(),
            "client"                 => $order->getClient()->getFirstName() . ' ' . $order->getClient()->getLastName(),
            "total"                  => $order->getTotalAmount(),
            "status"                 => $order->getStatus(),
            "date"                   => $order->getCreatedAt()->format('Y-m-d H:i:s'),
            "items"                  => $items,
            // ← NOUVEAU
            "fedapay_transaction_id" => $order->getFedapayTransactionId(),
            "delivery_type"    => $order->getDeliveryType(),
            "delivery_address" => $order->getDeliveryAddress(),
            "delivery_status"  => $order->getDeliveryStatus(),
            "fedapay_transaction_id" => $order->getFedapayTransactionId(),
        ]);
    }

    #[Route('/{id}/cancel', methods: ['POST'])]
    public function cancel(
        Order $order,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($order->getStatus() === 'cancelled') {
            return $this->json(["error" => "Commande déjà annulée"], 400);
        }
        if ($order->getStatus() === 'paid') {
            return $this->json(["error" => "Impossible d'annuler une commande déjà validée"], 400);
        }

        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();
            $product->setQuantity($product->getQuantity() + $item->getQuantity());
        }

        $order->setStatus('cancelled');
        $em->flush();

        return $this->json(["message" => "Commande annulée avec succès"]);
    }

    #[Route('/{id}/validate', methods: ['POST'])]
    public function validate(
        Order $order,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($order->getStatus() === 'paid') {
            return $this->json(["error" => "Commande déjà validée"], 400);
        }
        if ($order->getStatus() === 'cancelled') {
            return $this->json(["error" => "Impossible de valider une commande annulée"], 400);
        }

        $order->setStatus('paid');

        // ← NOUVEAU : créer le paiement automatiquement si pas déjà existant
        $existingPayment = $em->getRepository(Payment::class)
            ->findOneBy(['orders' => $order]);

        if (!$existingPayment) {
            $payment = new Payment();
            $payment->setClient($order->getClient());
            $payment->setOrders($order);
            $payment->setGym($order->getGym());
            $payment->setAmount($order->getTotalAmount());
            $payment->setPaymentDate(new \DateTime());
            $payment->setStatus('confirmed');

            // si commande boutique FedaPay → mobile_money, sinon especes
            $method = $order->getFedapayTransactionId()
                ? 'mobile_money'
                : 'especes';
            $payment->setPaymentMethod($method);

            // référence traçable
            $reference = $order->getFedapayTransactionId()
                ? 'FEDAPAY-' . $order->getFedapayTransactionId()
                : 'ORD-' . $order->getId() . '-' . uniqid();
            $payment->setReference($reference);

            $em->persist($payment);
        }

        $em->flush();

        return $this->json([
            "message"                => "Commande validée avec succès",
            "id"                     => $order->getId(),
            "status"                 => "paid",
            "fedapay_transaction_id" => $order->getFedapayTransactionId(),
            "payment_created"        => !$existingPayment,
        ]);
    }

    #[Route('/{id}/delivery', methods: ['POST'])]
    public function updateDelivery(
        Order $order,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data   = json_decode($request->getContent(), true);
        $status = $data['delivery_status'] ?? null;

        $allowed = ['pending', 'preparing', 'shipped', 'delivered'];
        if (!in_array($status, $allowed)) {
            return $this->json(['error' => 'Statut invalide'], 400);
        }

        $order->setDeliveryStatus($status);
        $em->flush();

        return $this->json([
            'message'         => 'Statut de livraison mis à jour',
            'id'              => $order->getId(),
            'delivery_status' => $status,
        ]);
    }
}
