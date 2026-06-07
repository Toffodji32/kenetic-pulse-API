<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Subscription;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment     $twig,
        private string          $projectDir,
        private LoggerInterface $logger,
        private string          $appBaseUrl,
    ) {}

    public function sendQrCodeToClient(Client $client): void
    {
        try {
            $qrCodePath = $this->projectDir . '/public/' . $client->getQrCode();

            $this->logger->info('Envoi email QR code', [
                'client_id' => $client->getId(),
                'email'     => $client->getEmail(),
            ]);

            // ← CID unique : l'image sera embarquée dans l'email
            // ✅ APRÈS — CID valide avec @
            $cid = 'qrcode_' . $client->getId() . '@kineticpulse.local';

            $html = $this->twig->render('emails/client_qrcode.html.twig', [
                'client'    => $client,
                'qrCodeUrl' => 'cid:' . $cid,  // ← le template reçoit "cid:qrcode_XXX"
            ]);

            $email = (new Email())
                ->from(new Address('toffodjiatchade@gmail.com', 'Kinetic Pulse'))
                ->to(new Address(
                    $client->getEmail(),
                    $client->getFirstName() . ' ' . $client->getLastName()
                ))
                ->subject('Kinetic Pulse — Votre QR code d\'accès')
                ->html($html);

            if (file_exists($qrCodePath)) {
                // ← Image embarquée inline (pas de dépendance URL externe)
                $email->addPart(
                    (new DataPart(new File($qrCodePath), 'qrcode_acces.png', 'image/png'))
                        ->asInline()
                        ->setContentId($cid)
                );
                $this->logger->debug('QR code embarqué en CID', [
                    'path' => $qrCodePath,
                    'cid'  => $cid,
                ]);
            } else {
                $this->logger->warning('QR code introuvable', ['path' => $qrCodePath]);
            }

            $this->mailer->send($email);

            $this->logger->info('Email envoyé avec succès', [
                'client_id' => $client->getId(),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur SMTP', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Erreur SMTP : ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Erreur email : ' . $e->getMessage());
        }
    }

    public function sendSubscriptionExpiredMail(Subscription $subscription): void
    {
        $client = $subscription->getClient();

        $html = $this->twig->render('emails/subscription_expired.html.twig', [
            'client'       => $client,
            'subscription' => $subscription,
        ]);

        $email = (new Email())
            ->from(new Address('toffodjiatchade@gmail.com', 'Kinetic Pulse'))
            ->to(new Address($client->getEmail(), $client->getFirstName() . ' ' . $client->getLastName()))
            ->subject('Kinetic Pulse — Votre abonnement a expiré')
            ->html($html);

        $this->mailer->send($email);
    }

    public function sendSubscriptionReminderMail(Subscription $subscription, int $daysLeft): void
    {
        $client = $subscription->getClient();

        $html = $this->twig->render('emails/subscription_reminder.html.twig', [
            'client'       => $client,
            'subscription' => $subscription,
            'daysLeft'     => $daysLeft,
        ]);

        $email = (new Email())
            ->from(new Address('toffodjiatchade@gmail.com', 'Kinetic Pulse'))
            ->to(new Address($client->getEmail(), $client->getFirstName() . ' ' . $client->getLastName()))
            ->subject("Kinetic Pulse — Votre abonnement expire dans $daysLeft jour(s)")
            ->html($html);

        $this->mailer->send($email);
    }
}
