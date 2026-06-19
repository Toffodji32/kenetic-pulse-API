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
            'plan' => $subscription->getPlan(),
            'trialEndsAt' => $subscription->getTrialEndsAt()?->format('Y-m-d H:i:s'),
            'startsAt' => $subscription->getStartsAt()?->format('Y-m-d H:i:s'),
            'endsAt' => $subscription->getEndsAt()?->format('Y-m-d H:i:s'),
            'daysLeft' => $daysLeft,
            'amount' => $subscription->getAmount(),
            'fedapayTransactionId' => $subscription->getFedapayTransactionId(),
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

        if (!$fedapayTransactionId) {
            return new JsonResponse(['error' => 'fedapayTransactionId requis'], 400);
        }

        // Vérification FedaPay (réutilise la logique existante)
        $verified = $this->verifyFedaPayTransaction($fedapayTransactionId);

        if (!$verified) {
            return new JsonResponse(['error' => 'Transaction FedaPay non approuvée'], 400);
        }

        $now = new \DateTime();

        if ($subscription->getStatus() === GymSubscription::STATUS_ACTIVE && $subscription->getEndsAt() > $now) {
            // Renouvellement anticipé : prolonge à partir de l'ancien endsAt
            $newEndsAt = (clone $subscription->getEndsAt())->modify('+1 month');
            $subscription->setEndsAt($newEndsAt);
        } else {
            // Nouvel abonnement ou réactivation
            $subscription->setStatus(GymSubscription::STATUS_ACTIVE);
            $subscription->setStartsAt($now);
            $subscription->setEndsAt((clone $now)->modify('+1 month'));
        }

        $subscription->setFedapayTransactionId($fedapayTransactionId);
        $subscription->setUpdatedAt($now);
        $this->em->flush();

        return $this->json([
            'status' => $subscription->getStatus(),
            'startsAt' => $subscription->getStartsAt()?->format('Y-m-d H:i:s'),
            'endsAt' => $subscription->getEndsAt()?->format('Y-m-d H:i:s'),
            'amount' => $subscription->getAmount(),
            'fedapayTransactionId' => $subscription->getFedapayTransactionId(),
        ]);
    }

    private function verifyFedaPayTransaction(string $transactionId): bool
    {
        $apiKey = $_ENV['FEDAPAY_SECRET_KEY'] ?? '';
        $env = $_ENV['FEDAPAY_ENV'] ?? 'sandbox';
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
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return false;
            }

            $data = json_decode($response, true);

            return isset($data['transaction']['status']) && $data['transaction']['status'] === 'approved';
        } catch (\Exception $e) {
            return false;
        }
    }
}
