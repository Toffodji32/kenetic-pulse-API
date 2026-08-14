<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Security\GymResolver;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Log\LoggerInterface;

#[Route('/api/clients')]
class ClientController extends AbstractController
{

    #[Route('', methods: ['GET'])]
    public function index(ClientRepository $clientRepository, GymResolver $gymResolver): JsonResponse
    {
        $gym = $gymResolver->getGym();
        if (!$gym) {
            return $this->json([]); // pas de gym → ne pas exposer les clients des autres salles
        }

        $clients = $clientRepository->findBy(['gym' => $gym]);

        $data = [];

        foreach ($clients as $client) {
            $data[] = [
                "id" => $client->getId(),
                "firstName" => $client->getFirstName(),
                "lastName" => $client->getLastName(),
                "phone" => $client->getPhone(),
                "email" => $client->getEmail(),
                "photo" => $client->getPhoto(),
                "qrCode" => $client->getQrCode(),
                "registrationDate" => $client->getRegistrationDate()?->format('Y-m-d H:i:s')
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Client $client, GymResolver $gymResolver): JsonResponse
    {
        $gym = $gymResolver->getGym();
        if (!$gym || $client->getGym()?->getId() !== $gym->getId()) {
            return $this->json(["error" => "Client introuvable"], 404);
        }

        $subscription = null;
        $status = "Aucun abonnement";

        if (!$client->getSubscriptions()->isEmpty()) {

            $subscription = $client->getSubscriptions()->last();

            if ($subscription->getEndDate() >= new \DateTime()) {
                $status = "Actif";
            } else {
                $status = "Expiré";
            }
        }

        return $this->json([
            "id" => $client->getId(),
            "firstName" => $client->getFirstName(),
            "lastName" => $client->getLastName(),
            "phone" => $client->getPhone(),
            "email" => $client->getEmail(),
            "photo" => $client->getPhoto(),
            "registrationDate" => $client->getRegistrationDate()?->format('Y-m-d H:i:s'),
            "qrCode" => $client->getQrCode(),
            "subscription" => $subscription ? [
                "type" => $subscription->getSubscriptionType()->getName(),
                "startDate" => $subscription->getStartDate()?->format('Y-m-d'),
                "endDate" => $subscription->getEndDate()?->format('Y-m-d'),
                "status" => $status
            ] : null
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        GymResolver $gymResolver,
        MailerService $mailerService,
        LoggerInterface $logger,
    ): JsonResponse {
        // ✅ Compatible avec FormData (image + champs)
        $data = $request->request->all();

        // ✅ Validation obligatoire
        if (
            empty($data['firstName']) ||
            empty($data['lastName']) ||
            empty($data['phone']) ||
            empty($data['email'])
        ) {
            return new JsonResponse([
                'error' => 'Tous les champs sont obligatoires'
            ], 400);
        }

        $gym = $gymResolver->getGym();
        if (!$gym) {
            return new JsonResponse(['error' => 'Aucune salle associée'], 403);
        }

        $client = new Client();
        $client->setGym($gym);

        $client->setFirstName($data['firstName']);
        $client->setLastName($data['lastName']);
        $client->setPhone($data['phone']);
        $client->setEmail($data['email']);
        $client->setRegistrationDate(new \DateTime());

        // ✅ UUID
        $uuid = Uuid::v4()->toRfc4122();
        $client->setUuid($uuid);

        // ✅ Upload photo (stocké en base — le filesystem Render est éphémère)
        $photoFile = $request->files->get('photo');

        if ($photoFile) {
            $photoData = base64_encode($photoFile->getContent());
            $photoMime = $photoFile->getMimeType() ?: 'image/jpeg';
            $fileName = uniqid() . '.' . $photoFile->guessExtension();

            $photoFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/clients',
                $fileName
            );

            $client->setPhoto('uploads/clients/' . $fileName);
            $client->setPhotoData($photoData);
            $client->setPhotoMime($photoMime);
        }

        $em->persist($client);
        $em->flush();

        // ✅ QR Code
        $qrPath = $this->generateQrCode($client);
        $client->setQrCode($qrPath);
        $em->flush();

        // ✅ Envoi du QR code par email (ne bloque jamais la création si l'envoi échoue)
        try {
            $mailerService->sendQrCodeToClient($client);
        } catch (\Exception $e) {
            $logger->warning('Echec de l\'envoi du QR code par email', [
                'client_id' => $client->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        return $this->json([
            "message" => "Client créé avec succès",
            "id" => $client->getId(),
            "qrCode" => $client->getQrCode(),
            "qrEmailSent" => true,
        ], 201);
    }

    // ← Accepte POST uniquement pour éviter le bug PUT+FormData
    #[Route('/{id}', methods: ['POST'])]
    public function update(Client $client, Request $request, EntityManagerInterface $em, GymResolver $gymResolver): JsonResponse
    {
        $gym = $gymResolver->getGym();
        if (!$gym || $client->getGym()?->getId() !== $gym->getId()) {
            return $this->json(["error" => "Client introuvable"], 404);
        }

        // Plus besoin des fallbacks — POST lit toujours bien $_POST
        $firstName = $request->request->get('firstName');
        $lastName  = $request->request->get('lastName');
        $phone     = $request->request->get('phone');
        $email     = $request->request->get('email');

        if ($firstName) $client->setFirstName($firstName);
        if ($lastName)  $client->setLastName($lastName);
        if ($phone)     $client->setPhone($phone);
        if ($email)     $client->setEmail($email);
        $photoFile = $request->files->get('photo');
        if ($photoFile) {
            $photoData = base64_encode($photoFile->getContent());
            $photoMime = $photoFile->getMimeType() ?: 'image/jpeg';
            $fileName = uniqid() . '.' . $photoFile->guessExtension();
            $photoFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/clients',
                $fileName
            );
            $client->setPhoto('uploads/clients/' . $fileName);
            $client->setPhotoData($photoData);
            $client->setPhotoMime($photoMime);
        }

        $em->flush();

        return $this->json([
            "message" => "Client modifié",
            "id"      => $client->getId(),
            "client"  => [
                "id"        => $client->getId(),
                "firstName" => $client->getFirstName(),
                "lastName"  => $client->getLastName(),
                "phone"     => $client->getPhone(),
                "email"     => $client->getEmail(),
                "photo"     => $client->getPhoto(),
                "qrCode"    => $client->getQrCode(),
            ]
        ]);
    }

    #[Route('/{id}/qr-code', methods: ['GET'])]
    public function qrCode(Client $client): Response
    {
        $result = (new Builder())->build(
            writer: new PngWriter(),
            data: json_encode([
                "uuid" => (string) $client->getUuid(),
                "name" => $client->getFirstName() . ' ' . $client->getLastName()
            ]),
            size: 300,
            margin: 10,
        );

        return new Response(
            $result->getString(),
            200,
            ['Content-Type' => 'image/png']
        );
    }

    #[Route('/{id}/photo', methods: ['GET'])]
    public function photo(Client $client): Response
    {
        if ($client->getPhotoData()) {
            return new Response(
                base64_decode($client->getPhotoData()),
                200,
                ['Content-Type' => $client->getPhotoMime() ?: 'image/jpeg']
            );
        }

        // Fallback : fichier statique (avant stockage en base)
        $path = $this->getParameter('kernel.project_dir') . '/public/' . $client->getPhoto();
        if ($client->getPhoto() && file_exists($path)) {
            return new Response(
                file_get_contents($path),
                200,
                ['Content-Type' => mime_content_type($path) ?: 'image/jpeg']
            );
        }

        return new Response('', 404);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Client $client, EntityManagerInterface $em, GymResolver $gymResolver): JsonResponse
    {
        $gym = $gymResolver->getGym();
        if (!$gym || $client->getGym()?->getId() !== $gym->getId()) {
            return $this->json(["error" => "Client introuvable"], 404);
        }

        $em->remove($client);
        $em->flush();

        return $this->json([
            "message" => "Client supprimé"
        ]);
    }

    private function generateQrCode(Client $client): string
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $publicPath = $projectDir . '/public/qrcodes';
        
        if (!file_exists($publicPath)) {
            mkdir($publicPath, 0755, true);
        }

        $path = 'qrcodes/client_' . $client->getUuid() . '.png';
        $fullPath = $publicPath . '/client_' . $client->getUuid() . '.png';

        $builder = new Builder();

        $result = $builder->build(
            writer: new PngWriter(),
            data: json_encode([
                "uuid" => (string) $client->getUuid(),
                "name" => $client->getFirstName() . ' ' . $client->getLastName()
            ]),
            size: 300,
            margin: 10,
        );

        $result->saveToFile($fullPath);

        return $path;
    }
}
