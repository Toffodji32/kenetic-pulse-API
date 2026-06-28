<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

#[Route('/api/shop')]
class ShopController extends AbstractController
{
    #[Route('/register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwt
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Tous les champs sont obligatoires'], 400);
        }

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return $this->json(['error' => 'Cet email est déjà utilisé'], 409);
        }

        $user = new User();
        $user->setName($data['name']);
        $user->setEmail($data['email']);
        $user->setRoles(['ROLE_CLIENT']);
        $user->setCreatedAt(new \DateTime());
        $user->setPassword($hasher->hashPassword($user, $data['password']));

        $em->persist($user);

        if (!empty($data['phone'])) {
            $client = new Client();
            $client->setFirstName($data['name']);
            $client->setLastName('');
            $client->setEmail($data['email']);
            $client->setPhone($data['phone']);
            $client->setRegistrationDate(new \DateTime());
            $client->setUuid(Uuid::v4()->toRfc4122());

            $em->persist($client);
        }

        $em->flush();

        $token = $jwt->create($user);

        return $this->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->getId(),
                'name'  => $user->getName(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ], 201);
    }

    #[Route('/products', methods: ['GET'])]
    public function products(ProductRepository $repo): JsonResponse
    {
        $products = $repo->findAll();
        $data     = [];

        foreach ($products as $p) {
            if ($p->getQuantity() <= 0) continue;
            $data[] = [
                'id'           => $p->getId(),
                'name'         => $p->getName(),
                'description'  => $p->getDescription(),
                'price'        => $p->getPrice(),
                'quantity'     => $p->getQuantity(),
                'image'        => $p->getImage(),
                'stock_status' => 'in_stock',
                'category'     => $p->getCategory()?->getName(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/orders', methods: ['POST'])]
    public function createOrder(
        Request $request,
        ProductRepository $productRepo,
        ClientRepository $clientRepo,
        EntityManagerInterface $em,
        #[CurrentUser] ?User $user
    ): JsonResponse {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $data            = json_decode($request->getContent(), true);
        $items           = $data['items']                   ?? [];
        $deliveryType    = $data['delivery_type']            ?? 'retrait';
        $deliveryAddress = $data['delivery_address']         ?? null;
        $deliveryFee     = (float) ($data['delivery_fee']    ?? 0);
        // ← NOUVEAU : récupérer l'ID transaction FedaPay
        $fedapayId       = $data['fedapay_transaction_id']   ?? null;

        if (empty($items)) {
            return $this->json(['error' => 'Panier vide'], 400);
        }

        if ($deliveryType === 'livraison' && empty($deliveryAddress)) {
            return $this->json(['error' => 'Adresse de livraison requise'], 400);
        }

        $gym = $user->getGym();
        if (!$gym) {
            return $this->json(['error' => 'Aucune salle associée à votre compte'], 403);
        }

        $client = $clientRepo->findOneBy(['email' => $user->getEmail()]);

        if (!$client) {
            $client = new Client();
            $client->setGym($gym);
            $client->setFirstName($user->getName());
            $client->setLastName('');
            $client->setEmail($user->getEmail());
            $client->setPhone('');
            $client->setRegistrationDate(new \DateTime());
            $client->setUuid(Uuid::v4()->toRfc4122());
            $em->persist($client);
            $em->flush();
        }

        $order = new Order();
        $order->setGym($gym);
        $order->setClient($client);
        $order->setCreatedAt(new \DateTime());
        $order->setStatus('pending');
        $order->setDeliveryType($deliveryType);
        if ($deliveryType === 'livraison') {
            $order->setDeliveryAddress($deliveryAddress);
            $order->setDeliveryStatus('pending');
        } else {
            $order->setDeliveryStatus(null); // retrait → pas de suivi livraison
        }

        // ← NOUVEAU : enregistrer l'ID FedaPay dans la commande
        if ($fedapayId) {
            $order->setFedapayTransactionId($fedapayId);
        }

        $total = 0;

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity  = (int) ($item['quantity'] ?? 1);

            $product = $productRepo->find($productId);

            if (!$product) {
                return $this->json(['error' => "Produit ID $productId introuvable"], 404);
            }

            if ($product->getQuantity() < $quantity) {
                return $this->json([
                    'error' => "Stock insuffisant pour {$product->getName()}"
                ], 400);
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

        $total += $deliveryFee;
        $order->setTotalAmount($total);

        $em->persist($order);
        $em->flush();

        return $this->json([
            'message'                => 'Commande passée avec succès',
            'order_id'               => $order->getId(),
            'total'                  => $total,
            'delivery_type'          => $deliveryType,
            'delivery_address'       => $deliveryAddress,
            'status'                 => 'pending',
            // ← NOUVEAU : retourner l'ID pour confirmation côté Vue
            'fedapay_transaction_id' => $order->getFedapayTransactionId(),
        ], 201);
    }

    #[Route('/orders', methods: ['GET'])]
    public function myOrders(
        ClientRepository $clientRepo,
        OrderRepository $orderRepo,
        #[CurrentUser] ?User $user
    ): JsonResponse {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $client = $clientRepo->findOneBy(['email' => $user->getEmail()]);

        if (!$client) {
            return $this->json([]);
        }

        $orders = $orderRepo->findBy(['client' => $client], ['id' => 'DESC']);
        $data   = [];

        foreach ($orders as $order) {
            $items = [];
            foreach ($order->getOrderItems() as $item) {
                $items[] = [
                    'product'  => $item->getProduct()->getName(),
                    'quantity' => $item->getQuantity(),
                    'price'    => $item->getPrice(),
                    'image'    => $item->getProduct()->getImage(),
                ];
            }
            $data[] = [
                'id'                     => $order->getId(),
                'total'                  => $order->getTotalAmount(),
                'status'                 => $order->getStatus(),
                'date'                   => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                'items'                  => $items,
                'fedapay_transaction_id' => $order->getFedapayTransactionId(),
                'delivery_type'          => $order->getDeliveryType(),
                'delivery_address'       => $order->getDeliveryAddress(),
                'delivery_status'        => $order->getDeliveryStatus(),
            ];
        }

        return $this->json($data);
    }
}
