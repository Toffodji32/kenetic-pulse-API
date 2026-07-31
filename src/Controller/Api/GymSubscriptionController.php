<?php

namespace App\Controller\Api;

use App\Entity\GymSubscription;
use App\Security\GymResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/gym/subscription')]
#[IsGranted('ROLE_ADMIN')]
class GymSubscriptionController extends AbstractController
{
    public function __construct(
        private GymResolver $gymResolver,
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'api_gym_subscription', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $gym = $this->gymResolver->getGym();

        if (!$gym) {
            return new JsonResponse(['error' => 'Gym non trouvée'], 404);
        }

        $subscription = $gym->getGymSubscription();

        if (!$subscription) {
            return new JsonResponse(['error' => 'Abonnement non trouvé'], 404);
        }

        $now = new \DateTime();

        $daysLeft = null;
        if ($subscription->getStatus() === GymSubscription::STATUS_TRIAL && $subscription->getTrialEndsAt() > $now) {
            $daysLeft = (int) $now->diff($subscription->getTrialEndsAt())->days;
        } elseif ($subscription->getStatus() === GymSubscription::STATUS_ACTIVE && $subscription->getEndsAt() > $now) {
            $daysLeft = (int) $now->diff($subscription->getEndsAt())->days;
        }

        return $this->json([
            'status' => $subscription->getStatus(),
            'planType' => $subscription->getPlanType(),
            'plan' => $subscription->getPlan(),
            'trialEndsAt' => $subscription->getTrialEndsAt()?->format('Y-m-d H:i:s'),
            'startsAt' => $subscription->getStartsAt()?->format('Y-m-d H:i:s'),
            'endsAt' => $subscription->getEndsAt()?->format('Y-m-d H:i:s'),
            'daysLeft' => $daysLeft,
            'amount' => $subscription->getAmount(),
            'fedapayTransactionId' => $subscription->getFedapayTransactionId(),
        ]);
    }

    #[Route('/plan', name: 'api_gym_subscription_change_plan', methods: ['POST'])]
    public function changePlan(Request $request): JsonResponse
    {
        $gym = $this->gymResolver->getGym();

        if (!$gym) {
            return new JsonResponse(['error' => 'Gym non trouvée'], 404);
        }

        $subscription = $gym->getGymSubscription();

        if (!$subscription) {
            return new JsonResponse(['error' => 'Abonnement non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $planType = $data['plan_type'] ?? null;

        if (!in_array($planType, ['basic', 'premium'], true)) {
            return new JsonResponse(['error' => 'plan_type doit être basic ou premium'], 400);
        }

        if ($subscription->getPlanType() === $planType) {
            return new JsonResponse(['error' => "L'abonnement est déjà en plan $planType"], 400);
        }

        $amount = $planType === 'premium' ? 25000 : 15000;

        $subscription->setPlanType($planType);
        $subscription->setAmount($amount);
        $subscription->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json([
            'message' => "Plan changé vers $planType",
            'status' => $subscription->getStatus(),
            'planType' => $subscription->getPlanType(),
            'plan' => $subscription->getPlan(),
            'amount' => $subscription->getAmount(),
            'endsAt' => $subscription->getEndsAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/pay', name: 'api_gym_subscription_pay', methods: ['POST'])]
    public function pay(Request $request): JsonResponse
    {
        $gym = $this->gymResolver->getGym();

        if (!$gym) {
            return new JsonResponse(['error' => 'Gym non trouvée'], 404);
        }

        $subscription = $gym->getGymSubscription();

        if (!$subscription) {
            return new JsonResponse(['error' => 'Abonnement non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $fedapayTransactionId = $data['fedapayTransactionId'] ?? null;
        $planType = $data['plan_type'] ?? 'basic';

        if (!in_array($planType, ['basic', 'premium'], true)) {
            return new JsonResponse(['error' => 'plan_type doit être basic ou premium'], 400);
        }

        if (!$fedapayTransactionId) {
            return new JsonResponse(['error' => 'fedapayTransactionId requis'], 400);
        }

        // Vérification FedaPay
        $verified = $this->verifyFedaPayTransaction($fedapayTransactionId);

        if (!$verified) {
            return new JsonResponse(['error' => 'Transaction FedaPay non approuvée'], 400);
        }

        $amount = $planType === 'premium' ? 25000 : 15000;
        $now = new \DateTime();

        if ($subscription->getStatus() === GymSubscription::STATUS_ACTIVE && $subscription->getEndsAt() > $now) {
            $newEndsAt = (clone $subscription->getEndsAt())->modify('+1 month');
            $subscription->setEndsAt($newEndsAt);
        } else {
            $subscription->setStatus(GymSubscription::STATUS_ACTIVE);
            $subscription->setStartsAt($now);
            $subscription->setEndsAt((clone $now)->modify('+1 month'));
        }

        $subscription->setPlanType($planType);
        $subscription->setAmount($amount);
        $subscription->setFedapayTransactionId($fedapayTransactionId);
        $subscription->setUpdatedAt($now);
        $this->em->flush();

        $endsAt = $subscription->getEndsAt();
        $daysLeft = $endsAt && $endsAt > $now ? (int) $now->diff($endsAt)->days : null;

        return $this->json([
            'status' => $subscription->getStatus(),
            'planType' => $subscription->getPlanType(),
            'plan' => $subscription->getPlan(),
            'startsAt' => $subscription->getStartsAt()?->format('Y-m-d H:i:s'),
            'endsAt' => $endsAt?->format('Y-m-d H:i:s'),
            'daysLeft' => $daysLeft,
            'amount' => $subscription->getAmount(),
            'fedapayTransactionId' => $subscription->getFedapayTransactionId(),
        ]);
    }

    private function verifyFedaPayTransaction(string $transactionId): bool
    {
        $apiKey = $_SERVER['FEDAPAY_SECRET_KEY'] ?? $_ENV['FEDAPAY_SECRET_KEY'] ?? '';
        $env = $_SERVER['FEDAPAY_ENV'] ?? $_ENV['FEDAPAY_ENV'] ?? 'sandbox';
        $baseUrl = $env === 'production'
            ? 'https://api.fedapay.com/v1'
            : 'https://sandbox-api.fedapay.com/v1';

        try {
            $ch = curl_init($baseUrl . '/transactions/' . $transactionId);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT => 15,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                return false;
            }

            $data = json_decode($response, true);

            // FedaPay API response: {"v1/transaction": {"id": ..., "status": "approved", ...}}
            // or for list endpoints: {"success": true, "transactions": [...]}
            $tx = $data['v1/transaction'] ?? $data['transaction'] ?? null;

            if (!$tx && isset($data['klass']) && str_starts_with($data['klass'], 'v1/transaction')) {
                $tx = $data;
            }

            return $tx && isset($tx['status']) && $tx['status'] === 'approved';
        } catch (\Exception $e) {
            return false;
        }
    }
}
