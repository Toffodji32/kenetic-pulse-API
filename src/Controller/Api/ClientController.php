<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use App\Service\MailerService; 
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

#[Route('/api/clients')]
class ClientController extends AbstractController
{

    #[Route('', methods: ['GET'])]
    public function index(ClientRepository $clientRepository): JsonResponse
    {
        $clients = $clientRepository->findAll();

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
    public function show(Client $client): JsonResponse
    {
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
    public function create(Request $request, EntityManagerInterface $em, MailerService $mailerService ): JsonResponse
    {
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

        $client = new Client();

        $client->setFirstName($data['firstName']);
        $client->setLastName($data['lastName']);
        $client->setPhone($data['phone']);
        $client->setEmail($data['email']);
        $client->setRegistrationDate(new \DateTime());

        // ✅ UUID
        $uuid = Uuid::v4()->toRfc4122();
        $client->setUuid($uuid);

        // ✅ Upload photo
        $photoFile = $request->files->get('photo');

        if ($photoFile) {
            $fileName = uniqid() . '.' . $photoFile->guessExtension();

            $photoFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/clients',
                $fileName
            );

            $client->setPhoto('uploads/clients/' . $fileName);
        }

        $em->persist($client);
        $em->flush();

        // ✅ QR Code
        $qrPath = $this->generateQrCode($client);
        $client->setQrCode($qrPath);
        $em->flush();

        // ← ENVOI EMAIL avec le QR code
        $emailSent = true;
        $emailError = null;
        $qrFullPath = $this->getParameter('kernel.project_dir') . '/public/' . $qrPath;

        if (!is_file($qrFullPath)) {
            $emailSent = false;
            $emailError = 'Le fichier QR code n\'a pas pu être créé sur le serveur';
        } else {
            try {
                $mailerService->sendQrCodeToClient($client);
            } catch (\Exception $e) {
                // on ne bloque pas la création si l'email échoue
                $emailSent = false;
                $emailError = (string) $e->getMessage();
            }
        }

        return $this->json([
            "message" => "Client créé avec succès",
            "id" => $client->getId(),
            "qrCode" => $client->getQrCode(),
            "emailSent" => $emailSent,
            "emailError" => $emailError ? (string) $emailError : null,  // ✅ Force string ou null
        ], 201);
    }

    // ← Accepte POST uniquement pour éviter le bug PUT+FormData
    #[Route('/{id}', methods: ['POST'])]
    public function update(Client $client, Request $request, EntityManagerInterface $em): JsonResponse
    {
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
            $fileName = uniqid() . '.' . $photoFile->guessExtension();
            $photoFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/clients',
                $fileName
            );
            $client->setPhoto('uploads/clients/' . $fileName);
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

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Client $client, EntityManagerInterface $em): JsonResponse
    {
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
