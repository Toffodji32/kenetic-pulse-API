<?php

namespace App\Command;

use App\Entity\GymSubscription;
use App\Repository\GymSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsCommand(
    name: 'app:check-gym-subscriptions',
    description: 'Vérifie les abonnements SaaS des salles de gym et notifie les propriétaires',
)]
class CheckGymSubscriptionsCommand extends Command
{
    public function __construct(
        private GymSubscriptionRepository $subscriptionRepo,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private Environment $twig,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Vérification des abonnements SaaS — ' . date('d/m/Y H:i'));

        $expiredCount = 0;
        $alertCount = 0;

        // 1. Expirer les abonnements en trial ou actifs dépassés
        $expiredSubscriptions = $this->subscriptionRepo->findAllExpired();

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->setStatus(GymSubscription::STATUS_EXPIRED);
            $subscription->setUpdatedAt(new \DateTime());
            $expiredCount++;
        }

        if ($expiredCount > 0) {
            $this->em->flush();
            $io->section(sprintf('%d abonnement(s) expiré(s)', $expiredCount));
        }

        // 2. Envoyer alertes pour les abonnements expirant dans moins de 3 jours
        $expiringSoon = $this->subscriptionRepo->findAllExpiringSoon(3);

        foreach ($expiringSoon as $subscription) {
            try {
                $this->sendExpirationAlert($subscription);
                $alertCount++;
            } catch (\Exception $e) {
                $io->warning(sprintf(
                    'Erreur envoi email pour gym #%d : %s',
                    $subscription->getGym()->getId(),
                    $e->getMessage()
                ));
            }
        }

        if ($alertCount > 0) {
            $io->section(sprintf('%d alerte(s) d\'expiration envoyée(s)', $alertCount));
        }

        $io->success(sprintf(
            'Terminé. %d expiré(s), %d alerte(s) envoyée(s).',
            $expiredCount,
            $alertCount
        ));

        return Command::SUCCESS;
    }

    private function sendExpirationAlert(GymSubscription $subscription): void
    {
        $gym = $subscription->getGym();
        $owner = $gym->getGymOwner();

        if (!$owner || !$owner->getEmail()) {
            return;
        }

        $now = new \DateTime();
        $referenceDate = $subscription->getStatus() === GymSubscription::STATUS_TRIAL
            ? $subscription->getTrialEndsAt()
            : $subscription->getEndsAt();

        $daysLeft = $now->diff($referenceDate)->days;

        $html = $this->twig->render('emails/gym_subscription_reminder.html.twig', [
            'ownerName' => $owner->getName(),
            'gymName' => $gym->getName(),
            'daysLeft' => $daysLeft,
            'status' => $subscription->getStatus(),
            'trialEndsAt' => $subscription->getTrialEndsAt(),
            'endsAt' => $subscription->getEndsAt(),
            'amount' => $subscription->getAmount(),
        ]);

        $email = (new Email())
            ->from(new Address('toffodjiatchade@gmail.com', 'Kinetic Pulse'))
            ->to(new Address($owner->getEmail(), $owner->getName()))
            ->subject("Kinetic Pulse — Votre abonnement expire dans {$daysLeft} jour(s)")
            ->html($html);

        $this->mailer->send($email);
    }
}
