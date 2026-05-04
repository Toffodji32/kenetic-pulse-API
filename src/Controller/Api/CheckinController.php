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
    #[Route('', methods: ['GET'])]
    public function index(
        \App\Repository\CheckinRepository $checkinRepo
    ): JsonResponse {
        $checkins = $checkinRepo->findBy([], ['checkinTime' => 'DESC'], 20);

        $data = [];
        foreach ($checkins as $checkin) {
            $data[] = [
                'id'          => $checkin->getId(),
                'client'      => $checkin->getClient()->getFirstName() . ' ' . $checkin->getClient()->getLastName(),
                'checkinTime' => $checkin->getCheckinTime()?->format('Y-m-d H:i:s'),
                'status'      => $checkin->getStatus(),
            ];
        }

        return $this->json($data);
    }
    
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

        // 🔎 vérifier abonnement actif
        $subscription = null;

        foreach ($client->getSubscriptions() as $sub) {
            if ($sub->getEndDate() >= new \DateTime()) {
                $subscription = $sub;
                break;
            }
        }

        if (!$subscription) {
            return $this->json([
                "error" => "Aucun abonnement actif"
            ], 403);
        }

        //  enregistrer checkin 
        $checkin = new Checkin();
        $checkin->setClient($client);
        $checkin->setCheckinTime(new \DateTime());
        $checkin->setStatus('present');

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
