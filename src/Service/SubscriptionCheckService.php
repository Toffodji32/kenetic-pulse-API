<?php

namespace App\Service;

use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SubscriptionCheckService
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepo,
        private MailerService          $mailerService,
        private EntityManagerInterface $em,
        private LoggerInterface        $logger,
    ) {}

    /**
     * Vérifie les abonnements et envoie les notifications :
     *  - mail d'expiration au client (abonnements déjà expirés, une seule fois)
     *  - mail de rappel au client (J-7 puis J-3 uniquement)
     *  - mail récapitulatif quotidien au propriétaire de la salle (expirations à 7 jours)
     *
     * @return array résumé structuré (exploitable par la commande et le contrôleur cron)
     */
    public function run(bool $dryRun = false): array
    {
        $today   = new \DateTime();
        $in3days = (new \DateTime())->modify('+3 days');
        $in7days = (new \DateTime())->modify('+7 days');

        $result = [
            'dry_run'         => $dryRun,
            'expired'         => [],
            'reminders'       => [],
            'owner_summaries' => [],
        ];

        $changed = false;

        // ── 1. Abonnements expirés → mail au client (une seule fois) ──────
        $expired = $this->subscriptionRepo->findExpired($today);
        foreach ($expired as $subscription) {
            if ($subscription->getExpiryMailSentAt() !== null) {
                continue;
            }
            try {
                if (!$dryRun) {
                    $this->mailerService->sendSubscriptionExpiredMail($subscription);
                    $subscription->setExpiryMailSentAt(new \DateTime());
                    $this->createNotification(
                        $subscription->getGym(),
                        'subscription_expired',
                        'Abonnement expiré',
                        sprintf(
                            '%s (%s) — abonnement expiré le %s',
                            $this->clientName($subscription),
                            $subscription->getSubscriptionType()->getName(),
                            $subscription->getEndDate()->format('d/m/Y')
                        )
                    );
                    $changed = true;
                }
                $result['expired'][] = $this->clientInfo($subscription, ['expired' => true]);
            } catch (\Exception $e) {
                $this->logger->warning('Erreur mail expiration', [
                    'subscription_id' => $subscription->getId(),
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        // ── 2. Rappels clients J-7 puis J-3 (une fois par palier) ─────────
        $expiring7 = $this->subscriptionRepo->findExpiringSoon($today, $in7days);
        $expiring3 = $this->subscriptionRepo->findExpiringSoon($today, $in3days);

        foreach ($expiring7 as $subscription) {
            if ($this->sendReminder($subscription, 7, $dryRun, $result)) {
                $changed = true;
            }
        }
        foreach ($expiring3 as $subscription) {
            if ($this->sendReminder($subscription, 3, $dryRun, $result)) {
                $changed = true;
            }
        }

        // ── 3. Récapitulatif quotidien aux propriétaires de salle ─────────
        $allByGym = [];
        foreach (array_merge($expiring3, $expiring7) as $subscription) {
            $gym = $subscription->getGym();
            if ($gym) {
                $allByGym[$gym->getId()][$subscription->getId()] = $subscription;
            }
        }

        foreach ($allByGym as $gymId => $subscriptions) {
            $gym   = reset($subscriptions)->getGym();
            $owner = $gym->getGymOwner();
            if (!$owner || !$owner->getEmail()) {
                $this->logger->info('Récap propriétaire ignoré (pas d\'email)', ['gym_id' => $gymId]);
                continue;
            }

            $items = [];
            foreach ($subscriptions as $subscription) {
                $items[] = [
                    'subscription' => $subscription,
                    'daysLeft'     => $this->daysLeft($subscription),
                ];
            }

            try {
                if (!$dryRun) {
                    $this->mailerService->sendSubscriptionExpirySummaryMail($gym, $items);
                }
                $result['owner_summaries'][] = [
                    'gym_id'        => $gym->getId(),
                    'gym'           => $gym->getName(),
                    'owner_email'   => $owner->getEmail(),
                    'subscriptions' => count($items),
                ];
            } catch (\Exception $e) {
                $this->logger->warning('Erreur mail récap propriétaire', [
                    'gym_id' => $gymId,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        if ($changed && !$dryRun) {
            $this->em->flush();
        }

        return $result;
    }

    private function sendReminder($subscription, int $daysLeft, bool $dryRun, array &$result): bool
    {
        $lastSent = $subscription->getLastReminderDays();
        if ($lastSent !== null && $daysLeft >= $lastSent) {
            return false;
        }

        try {
            if (!$dryRun) {
                $this->mailerService->sendSubscriptionReminderMail($subscription, $daysLeft);
                $subscription->setLastReminderDays($daysLeft);
                $this->createNotification(
                    $subscription->getGym(),
                    'subscription_expiring',
                    sprintf('Abonnement expire dans %d jour%s', $daysLeft, $daysLeft > 1 ? 's' : ''),
                    sprintf(
                        '%s (%s) — expire le %s',
                        $this->clientName($subscription),
                        $subscription->getSubscriptionType()->getName(),
                        $subscription->getEndDate()->format('d/m/Y')
                    )
                );
            }
            $result['reminders'][] = $this->clientInfo($subscription, ['days_left' => $daysLeft]);
            return true;
        } catch (\Exception $e) {
            $this->logger->warning('Erreur mail rappel', [
                'subscription_id' => $subscription->getId(),
                'error'           => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function clientInfo($subscription, array $extra = []): array
    {
        $client = $subscription->getClient();
        return array_merge([
            'client_id'  => $client->getId(),
            'client'     => trim($client->getFirstName() . ' ' . $client->getLastName()),
            'email'      => $client->getEmail(),
            'expires_on' => $subscription->getEndDate()->format('Y-m-d'),
        ], $extra);
    }

    private function clientName($subscription): string
    {
        $client = $subscription->getClient();
        return trim($client->getFirstName() . ' ' . $client->getLastName());
    }

    private function createNotification(?\App\Entity\Gym $gym, string $type, string $title, string $message): void
    {
        if (!$gym) {
            return;
        }
        $notification = new \App\Entity\Notification();
        $notification->setGym($gym);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $this->em->persist($notification);
    }

    private function daysLeft($subscription): int
    {
        $diff = $subscription->getEndDate()->getTimestamp() - (new \DateTime())->getTimestamp();
        return max(0, (int) ceil($diff / 86400));
    }
}