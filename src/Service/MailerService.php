<?php

namespace App\Service;

use App\Entity\Client;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment     $twig,
        private string          $projectDir,
        private LoggerInterface $logger,
    ) {}

    public function sendQrCodeToClient(Client $client): void
    {
        try {
            $qrCodePath = $this->projectDir . '/public/' . $client->getQrCode();

            $this->logger->info('Envoi email QR code', [
                'client_id' => $client->getId(),
                'email'     => $client->getEmail(),
            ]);

            // ← URL publique pour afficher le QR dans le template
            $qrCodeUrl = 'http://127.0.0.1:8000/' . $client->getQrCode();

            // ← Rendu du template avec l'URL publique (pas de CID)
            $html = $this->twig->render('emails/client_qrcode.html.twig', [
                'client'    => $client,
                'qrCodeUrl' => $qrCodeUrl,
            ]);

            // ← Créer l'email proprement avec Address()
            $email = (new Email())
                ->from(new Address('noreply@kineticpulse.com', 'Kinetic Pulse'))
                ->to(new Address(
                    $client->getEmail(),
                    $client->getFirstName() . ' ' . $client->getLastName()
                ))
                ->subject('Kinetic Pulse — Votre QR code d\'accès')
                ->html($html);

            // ← Attacher le QR code en pièce jointe
            if (file_exists($qrCodePath)) {
                $email->attachFromPath(
                    $qrCodePath,
                    'qrcode_acces.png',
                    'image/png'
                );
                $this->logger->debug('QR code attaché', ['path' => $qrCodePath]);
            } else {
                $this->logger->warning('QR code introuvable', ['path' => $qrCodePath]);
            }

            $this->mailer->send($email);

            $this->logger->info('Email envoyé avec succès', [
                'client_id' => $client->getId(),
            ]);

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur SMTP', [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Erreur SMTP : ' . $e->getMessage());

        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email', [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Erreur email : ' . $e->getMessage());
        }
    }
}