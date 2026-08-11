<?php

namespace App\Controller;

use App\Entity\WebhookEvent;
use App\Service\MailerService;
use App\Service\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/webhooks')]
class WebhookController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private WalletService $walletService,
        private MailerService $mailerService,
        private LoggerInterface $logger,
    ) {}

    #[Route('/fedapay', name: 'api_webhook_fedapay', methods: ['POST'])]
    public function handleFedapay(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        // 1. Verify HMAC signature
        $signature = $request->headers->get('X-Fedapay-Signature', '');
        $secret = $_ENV['FEDAPAY_WEBHOOK_SECRET']
            ?? $_SERVER['FEDAPAY_WEBHOOK_SECRET']
            ?? getenv('FEDAPAY_WEBHOOK_SECRET')
            ?? '';
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $signature)) {
            return new JsonResponse(['error' => 'Invalid signature'], 401);
        }

        // 2. Parse body
        $payload = json_decode($rawBody, true);
        if (!$payload || !isset($payload['event']['id'])) {
            return new JsonResponse(['error' => 'Invalid payload'], 400);
        }

        $eventId = $payload['event']['id'];
        $eventType = $payload['event']['type'] ?? 'unknown';

        // 3. Idempotence check
        $existing = $this->em->getRepository(WebhookEvent::class)->findOneBy(['fedapayEventId' => $eventId]);
        if ($existing) {
            return new JsonResponse(['status' => 'already_processed']);
        }

        // 4. Record WebhookEvent immediately
        $webhookEvent = new WebhookEvent();
        $webhookEvent->setFedapayEventId($eventId);
        $webhookEvent->setEventType($eventType);
        $webhookEvent->setPayload($payload);
        $webhookEvent->setStatus(WebhookEvent::STATUS_PROCESSED);
        $webhookEvent->setProcessedAt(new \DateTime());
        $this->em->persist($webhookEvent);
        $this->em->flush();

        try {
            switch ($eventType) {
                case 'transaction.approved':
                    $this->handleTransactionApproved($payload, $webhookEvent);
                    break;

                case 'transfer.completed':
                    $this->handleTransferCompleted($payload, $webhookEvent);
                    break;

                default:
                    $webhookEvent->setStatus(WebhookEvent::STATUS_IGNORED);
                    $this->em->flush();
                    $this->logger->info('Webhook ignored', ['event_type' => $eventType, 'event_id' => $eventId]);
                    break;
            }
        } catch (\Exception $e) {
            $webhookEvent->setStatus(WebhookEvent::STATUS_FAILED);
            $this->em->flush();
            $this->logger->error('Webhook processing failed', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function handleTransactionApproved(array $payload, WebhookEvent $webhookEvent): void
    {
        $data = $payload['event']['data'] ?? $payload['data'] ?? [];
        $metadata = $data['metadata'] ?? [];
        $gymId = $metadata['gym_id'] ?? null;

        $gym = null;

        if ($gymId) {
            $gym = $this->em->getRepository(\App\Entity\Gym::class)->find($gymId);
            if (!$gym) {
                $this->logger->error('Webhook gym not found', ['gym_id' => $gymId, 'event_id' => $webhookEvent->getFedapayEventId()]);
                return;
            }
        }

        // Fallback : retrouver le gym via l'email du client (boutique par salle / globale)
        if (!$gym) {
            $customerEmail = $data['customer']['email'] ?? null;
            if ($customerEmail) {
                $user = $this->em->getRepository(\App\Entity\User::class)->findOneByEmail($customerEmail);
                $gym = $user?->getGym();
            }
            if (!$gym) {
                $this->logger->warning('Webhook transaction.approved : gym introuvable (pas de gym_id ni d\'email client connu)', [
                    'event_id' => $webhookEvent->getFedapayEventId(),
                ]);
                return;
            }
        }

        $amount = (int) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            $this->logger->warning('Webhook invalid amount', ['amount' => $amount, 'event_id' => $webhookEvent->getFedapayEventId()]);
            return;
        }

        $reference = $data['reference'] ?? $data['id'] ?? $webhookEvent->getFedapayEventId();

        // Anti double-crédit : si la validation admin a déjà crédité (même référence), on saute
        $alreadyCredited = $this->em->getRepository(\App\Entity\WalletTransaction::class)
            ->findOneBy(['reference' => (string) $reference, 'type' => \App\Entity\WalletTransaction::TYPE_CREDIT]);
        if ($alreadyCredited) {
            $this->logger->info('Webhook transaction.approved déjà créditée, ignorée', [
                'reference' => $reference,
                'event_id' => $webhookEvent->getFedapayEventId(),
            ]);
            return;
        }

        $this->walletService->credit(
            $gym,
            $amount,
            (string) $reference,
            'Paiement client — transaction FedaPay #' . $reference,
            ['fedapay_event_id' => $webhookEvent->getFedapayEventId()]
        );

        $this->notifyGymCredit($gym, $amount, $reference);
    }

    private function handleTransferCompleted(array $payload, WebhookEvent $webhookEvent): void
    {
        $data = $payload['event']['data'] ?? $payload['data'] ?? [];
        $transferId = $data['id'] ?? $data['reference'] ?? null;

        if (!$transferId) {
            $this->logger->warning('Webhook transfer.completed missing transfer ID', ['event_id' => $webhookEvent->getFedapayEventId()]);
            return;
        }

        $withdrawal = $this->em->getRepository(\App\Entity\WithdrawalRequest::class)
            ->findOneBy(['fedapayTransferId' => $transferId]);

        if (!$withdrawal) {
            $this->logger->warning('Webhook transfer.completed: WithdrawalRequest not found', ['transfer_id' => $transferId]);
            return;
        }

        if ($withdrawal->getStatus() === \App\Entity\WithdrawalRequest::STATUS_PROCESSING) {
            $this->walletService->completeWithdrawal($withdrawal);
        }
    }

    private function notifyGymCredit(\App\Entity\Gym $gym, int $amount, string $reference): void
    {
        try {
            $owner = $gym->getGymOwner();
            if (!$owner || !$owner->getEmail()) {
                return;
            }

            $html = sprintf(
                '<p>Votre wallet a été crédité de <strong>%d FCFA</strong>.</p><p>Référence : %s</p><p>Solde disponible : %d FCFA</p>',
                $amount,
                htmlspecialchars($reference),
                $gym->getGymWallet()?->getBalanceAvailable() ?? 0
            );

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address('toffodjiatchade@gmail.com', 'Kinetic Pulse'))
                ->to(new \Symfony\Component\Mime\Address($owner->getEmail(), $owner->getName()))
                ->subject('Kinetic Pulse — Crédit de ' . number_format($amount, 0, ',', ' ') . ' FCFA sur votre wallet')
                ->html($html);

            $this->mailerService->sendRaw($email);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to send credit notification email', [
                'gym_id' => $gym->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
