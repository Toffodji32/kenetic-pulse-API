<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Entity\Gym;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/shop/{gymSlug}')]
class GymShopController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private JWTTokenManagerInterface $jwtManager,
    ) {}

    private function resolveGym(string $gymSlug): ?Gym
    {
        return $this->em->getRepository(Gym::class)->findOneBySlug($gymSlug);
    }

    #[Route('/products', name: 'api_gym_shop_products', methods: ['GET'])]
    public function products(string $gymSlug): JsonResponse
    {
        $gym = $this->resolveGym($gymSlug);
        if (!$gym) {
            return new JsonResponse(['error' => 'Salle non trouvée'], 404);
        }

        $products = $this->em->getRepository(\App\Entity\Product::class)
            ->findBy(['gym' => $gym]);

        $data = array_map(function (\App\Entity\Product $p) {
            return [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'description' => $p->getDescription(),
                'price' => $p->getPrice(),
                'quantity' => $p->getQuantity(),
                'image' => $p->getImage(),
                'stock_status' => $p->getQuantity() > 0 ? ($p->getQuantity() < 5 ? 'low' : 'in_stock') : 'out_of_stock',
                'category' => $p->getCategory()?->getName(),
                'category_id' => $p->getCategory()?->getId(),
            ];
        }, $products);

        return $this->json($data);
    }

    #[Route('/categories', name: 'api_gym_shop_categories', methods: ['GET'])]
    public function categories(string $gymSlug): JsonResponse
    {
        $gym = $this->resolveGym($gymSlug);
        if (!$gym) {
            return new JsonResponse(['error' => 'Salle non trouvée'], 404);
        }

        $categories = $this->em->getRepository(\App\Entity\Category::class)
            ->createQueryBuilder('c')
            ->where('c.gym = :gym OR c.gym IS NULL')
            ->setParameter('gym', $gym)
            ->getQuery()
            ->getResult();

        $data = array_map(function (\App\Entity\Category $c) {
            return [
                'id' => $c->getId(),
                'name' => $c->getName(),
                'description' => $c->getDescription(),
            ];
        }, $categories);

        return $this->json($data);
    }

    #[Route('/register', name: 'api_gym_shop_register', methods: ['POST'])]
    public function register(Request $request, string $gymSlug): JsonResponse
    {
        $gym = $this->resolveGym($gymSlug);
        if (!$gym) {
            return new JsonResponse(['error' => 'Salle non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $password = $data['password'] ?? null;

        if (!$name || !$email || !$password) {
            return new JsonResponse(['error' => 'name, email et password requis'], 400);
        }

        $existing = $this->em->getRepository(User::class)->findOneByEmail($email);
        if ($existing) {
            return new JsonResponse(['error' => 'Cet email est déjà utilisé'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_CLIENT']);
        $user->setCreatedAt(new \DateTime());
        $user->setGym($gym);
        $this->em->persist($user);

        $client = new Client();
        $client->setFirstName($name);
        $client->setLastName('');
        $client->setEmail($email);
        $client->setPhone($phone ?? '');
        $client->setGym($gym);
        $client->setRegistrationDate(new \DateTime());
        $this->em->persist($client);

        $this->em->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ], 201);
    }

    #[Route('/orders', name: 'api_gym_shop_orders', methods: ['POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')"))]
    public function createOrder(Request $request, string $gymSlug): JsonResponse
    {
        $gym = $this->resolveGym($gymSlug);
        if (!$gym) {
            return new JsonResponse(['error' => 'Salle non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $items = $data['items'] ?? [];
        $deliveryType = $data['delivery_type'] ?? 'retrait';
        $deliveryAddress = $data['delivery_address'] ?? null;
        $deliveryFee = $data['delivery_fee'] ?? 0;
        $fedapayTransactionId = $data['fedapay_transaction_id'] ?? null;

        if (empty($items)) {
            return new JsonResponse(['error' => 'Panier vide'], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $client = $this->em->getRepository(Client::class)->findOneBy([
            'email' => $user->getEmail(),
            'gym' => $gym,
        ]);

        if (!$client) {
            $client = new Client();
            $client->setFirstName($user->getName());
            $client->setLastName('');
            $client->setEmail($user->getEmail());
            $client->setPhone('');
            $client->setGym($gym);
            $client->setRegistrationDate(new \DateTime());
            $this->em->persist($client);
        }

        $totalAmount = 0;
        $orderItems = [];

        foreach ($items as $item) {
            $product = $this->em->getRepository(\App\Entity\Product::class)->find($item['product_id'] ?? null);
            if (!$product || $product->getGym()->getId() !== $gym->getId()) {
                return new JsonResponse(['error' => 'Produit invalide : ' . ($item['product_id'] ?? 'null')], 400);
            }

            $qty = (int) ($item['quantity'] ?? 1);
            if ($product->getQuantity() < $qty) {
                return new JsonResponse(['error' => 'Stock insuffisant pour ' . $product->getName()], 400);
            }

            $product->setQuantity($product->getQuantity() - $qty);
            $lineTotal = $product->getPrice() * $qty;
            $totalAmount += $lineTotal;

            $orderItems[] = [
                'product' => $product,
                'quantity' => $qty,
                'price' => $product->getPrice(),
            ];
        }

        $totalAmount += (float) $deliveryFee;

        $order = new Order();
        $order->setClient($client);
        $order->setGym($gym);
        $order->setTotalAmount($totalAmount);
        $order->setStatus('pending');
        $order->setDeliveryType($deliveryType);
        $order->setDeliveryAddress($deliveryAddress);
        $order->setFedapayTransactionId($fedapayTransactionId);
        $order->setCreatedAt(new \DateTime());
        $this->em->persist($order);

        foreach ($orderItems as $oi) {
            $orderItem = new OrderItem();
            $orderItem->setOrders($order);
            $orderItem->setProduct($oi['product']);
            $orderItem->setQuantity($oi['quantity']);
            $orderItem->setPrice($oi['price']);
            $this->em->persist($orderItem);
        }

        $this->em->flush();

        return $this->json([
            'order_id' => $order->getId(),
            'total' => $order->getTotalAmount(),
            'status' => $order->getStatus(),
        ], 201);
    }

    #[Route('/orders', name: 'api_gym_shop_orders_list', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')"))]
    public function myOrders(string $gymSlug): JsonResponse
    {
        $gym = $this->resolveGym($gymSlug);
        if (!$gym) {
            return new JsonResponse(['error' => 'Salle non trouvée'], 404);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $client = $this->em->getRepository(Client::class)->findOneBy([
            'email' => $user->getEmail(),
            'gym' => $gym,
        ]);

        if (!$client) {
            return $this->json([]);
        }

        $orders = $this->em->getRepository(Order::class)
            ->findBy(['client' => $client, 'gym' => $gym], ['createdAt' => 'DESC']);

        $data = array_map(function (Order $order) {
            $items = $order->getOrderItems()->map(function (OrderItem $item) {
                return [
                    'product_id' => $item->getProduct()->getId(),
                    'product_name' => $item->getProduct()->getName(),
                    'product_image' => $item->getProduct()->getImage(),
                    'quantity' => $item->getQuantity(),
                    'price' => $item->getPrice(),
                    'subtotal' => $item->getQuantity() * $item->getPrice(),
                ];
            })->toArray();

            return [
                'id' => $order->getId(),
                'total' => $order->getTotalAmount(),
                'status' => $order->getStatus(),
                'delivery_type' => $order->getDeliveryType(),
                'delivery_address' => $order->getDeliveryAddress(),
                'delivery_status' => $order->getDeliveryStatus(),
                'fedapay_transaction_id' => $order->getFedapayTransactionId(),
                'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                'items' => $items,
            ];
        }, $orders);

        return $this->json($data);
    }
}
