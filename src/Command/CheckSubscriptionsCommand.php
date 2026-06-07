<?php
namespace App\Command;

use App\Repository\SubscriptionRepository;
use App\Service\MailerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-subscriptions',
    description: 'Vérifie les abonnements et notifie les clients',
)]
class CheckSubscriptionsCommand extends Command
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepo,
        private MailerService          $mailerService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Vérification des abonnements — ' . date('d/m/Y H:i'));

        $today   = new \DateTime();
        $in3days = (new \DateTime())->modify('+3 days');
        $in7days = (new \DateTime())->modify('+7 days');

        // ── 1. Expirés ────────────────────────────────────────────────────
        $expired = $this->subscriptionRepo->findExpired($today);
        $io->section(sprintf('%d abonnement(s) expiré(s)', count($expired)));

        foreach ($expired as $subscription) {
            try {
                $this->mailerService->sendSubscriptionExpiredMail($subscription);
                $io->text(sprintf(
                    '  [EXPIRÉ] %s %s → %s',
                    $subscription->getClient()->getFirstName(),
                    $subscription->getClient()->getLastName(),
                    $subscription->getClient()->getEmail(),
                ));
            } catch (\Exception $e) {
                $io->warning(sprintf(
                    '  Erreur mail %s : %s',
                    $subscription->getClient()->getEmail(),
                    $e->getMessage()
                ));
            }
        }

        // ── 2. Expirant dans 3 jours ──────────────────────────────────────
        $expiring3 = $this->subscriptionRepo->findExpiringSoon($today, $in3days);
        $io->section(sprintf('%d abonnement(s) expirant dans 3 jours', count($expiring3)));

        foreach ($expiring3 as $subscription) {
            try {
                $this->mailerService->sendSubscriptionReminderMail($subscription, 3);
                $io->text(sprintf(
                    '  [RAPPEL 3j] %s %s → %s',
                    $subscription->getClient()->getFirstName(),
                    $subscription->getClient()->getLastName(),
                    $subscription->getClient()->getEmail(),
                ));
            } catch (\Exception $e) {
                $io->warning(sprintf(
                    '  Erreur mail %s : %s',
                    $subscription->getClient()->getEmail(),
                    $e->getMessage()
                ));
            }
        }

        // ── 3. Expirant dans 7 jours ──────────────────────────────────────
        $expiring7 = $this->subscriptionRepo->findExpiringSoon($in3days, $in7days);
        $io->section(sprintf('%d abonnement(s) expirant dans 7 jours', count($expiring7)));

        foreach ($expiring7 as $subscription) {
            try {
                $this->mailerService->sendSubscriptionReminderMail($subscription, 7);
                $io->text(sprintf(
                    '  [RAPPEL 7j] %s %s → %s',
                    $subscription->getClient()->getFirstName(),
                    $subscription->getClient()->getLastName(),
                    $subscription->getClient()->getEmail(),
                ));
            } catch (\Exception $e) {
                $io->warning(sprintf(
                    '  Erreur mail %s : %s',
                    $subscription->getClient()->getEmail(),
                    $e->getMessage()
                ));
            }
        }

        $io->success('Vérification terminée.');
        return Command::SUCCESS;
    }
}