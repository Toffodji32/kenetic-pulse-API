<?php

namespace App\Controller\Api;

use App\Service\SubscriptionCheckService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints publics protégés par un token (X-Cron-Token), appelés par un cron externe.
 */
#[Route('/api/cron')]
class CronController extends AbstractController
{
    public function __construct(
        private SubscriptionCheckService $checkService,
    ) {}

    #[Route('/check-subscriptions', name: 'api_cron_check_subscriptions', methods: ['POST'])]
    public function checkSubscriptions(Request $request): JsonResponse
    {
        $token  = $request->headers->get('X-Cron-Token', '');
        $secret = (string) ($_ENV['CRON_JOB_TOKEN'] ?? $_SERVER['CRON_JOB_TOKEN'] ?? getenv('CRON_JOB_TOKEN') ?? '');

        if ($secret === '' || !hash_equals($secret, $token)) {
            return $this->json(['error' => 'Invalid token'], 401);
        }

        $result = $this->checkService->run();

        return $this->json([
            'status' => 'ok',
            'result' => $result,
        ]);
    }
}
