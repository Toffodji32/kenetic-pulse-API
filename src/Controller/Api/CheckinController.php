<?php

namespace App\Controller\Api;

use App\Entity\Checkin;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/checkin')]
class CheckinController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function checkin(
        Request $request,
        ClientRepository $clientRepository,
        EntityManagerInterface $em
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        $uuid = $data['uuid'] ?? null;

        if (!$uuid) {
            return $this->json([
                "error" => "UUID requis"
            ], 400);
        }

        // 🔍 chercher client
        $client = $clientRepository->findOneBy(['uuid' => $uuid]);

        if (!$client) {
            return $this->json([
                "error" => "Client introuvable"
            ], 404);
        }

        // 🔎 vérifier abonnement
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

        if ($status !== "Actif") {
            return $this->json([
                "error" => "Abonnement invalide",
                "status" => $status
            ], 403);
        }

        // ✅ enregistrer checkin
        $checkin = new Checkin();
        $checkin->setClient($client);
        $checkin->setCheckinTime(new \DateTime());
        $checkin->setStatus('authorized');

        $em->persist($checkin);
        $em->flush();

        return $this->json([
            "message" => "Accès autorisé",
            "client" => $client->getFirstName() . ' ' . $client->getLastName(),
            "subscription" => $subscription->getSubscriptionType()->getName(),
            "checkinTime" => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }
}