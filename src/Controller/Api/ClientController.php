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
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $client = new Client();

        $client->setFirstName($data['firstName'] ?? null);
        $client->setLastName($data['lastName'] ?? null);
        $client->setPhone($data['phone'] ?? null);
        $client->setEmail($data['email'] ?? null);
        $client->setRegistrationDate(new \DateTime());

        // génération UUID
        $uuid = Uuid::v4()->toRfc4122();
        $client->setUuid($uuid);

        


        // upload photo
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

        // génération QR code
        $qrPath = $this->generateQrCode($client);

        $client->setQrCode($qrPath);

        $em->flush();

        return $this->json([
            "message" => "Client créé avec succès",
            "id" => $client->getId(),
            "qrCode" => $client->getQrCode()
        ], 201);
    }


    #[Route('/{id}', methods: ['PUT'])]
    public function update(Client $client, Request $request, EntityManagerInterface $em): JsonResponse
    {

        if ($request->request->get('firstName')) {
            $client->setFirstName($request->request->get('firstName'));
        }

        if ($request->request->get('lastName')) {
            $client->setLastName($request->request->get('lastName'));
        }

        if ($request->request->get('phone')) {
            $client->setPhone($request->request->get('phone'));
        }

        if ($request->request->get('email')) {
            $client->setEmail($request->request->get('email'));
        }

        // modifier photo
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
            "client" => [
                "id" => $client->getId(),
                "firstName" => $client->getFirstName(),
                "lastName" => $client->getLastName(),
                "phone" => $client->getPhone(),
                "email" => $client->getEmail(),
                "photo" => $client->getPhoto()
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

        if (!file_exists('public/qrcodes')) {
            mkdir('public/qrcodes', 0777, true);
        }

        $path = 'qrcodes/client_' . $client->getUuid() . '.png';

        // Note: endroid/qr-code v6 does not provide a static Builder::create() method.
        // Instead we instantiate the builder and call build() using named parameters.
        $builder = new Builder();

        $result = $builder->build(
            writer: new PngWriter(),
            data: 'CLIENT-' . $client->getUuid(),
            size: 300,
            margin: 10,
        );

        $result->saveToFile('public/' . $path);

        return $path;
    }

}