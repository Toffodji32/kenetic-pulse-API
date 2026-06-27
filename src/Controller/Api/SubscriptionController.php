<?php

namespace App\Controller\Api;

use App\Entity\Subscription;
use App\Repository\ClientRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\SubscriptionTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/subscriptions')]
class SubscriptionController extends AbstractController
{
    // ── CREATE ────────────────────────────────────────────────────────────
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        ClientRepository $clientRepository,
        SubscriptionTypeRepository $typeRepository,
        EntityManagerInterface $em
    ): JsonResponse {

        $data     = json_decode($request->getContent(), true);
        $clientId = $data['client_id'] ?? null;
        $typeId   = $data['subscription_type_id'] ?? null;

        if (!$clientId || !$typeId) {
            return $this->json(['error' => 'client_id et subscription_type_id requis'], 400);
        }

        $client = $clientRepository->find($clientId);
        if (!$client) {
            return $this->json(['error' => 'Client introuvable'], 404);
        }

        $type = $typeRepository->find($typeId);
        if (!$type) {
            return $this->json(['error' => 'Type abonnement introuvable'], 404);
        }

        $startDate = new \DateTime();
        $endDate   = (clone $startDate)->modify('+' . $type->getDurationDays() . ' days');
        $status    = ($endDate >= new \DateTime()) ? 'actif' : 'expire';

        $subscription = new Subscription();
        $subscription->setClient($client);
        $subscription->setSubscriptionType($type);
        $subscription->setStartDate($startDate);
        $subscription->setEndDate($endDate);
        $subscription->setStatus($status);
        $subscription->setPrice($type->getPrice());
        $subscription->setGym($client->getGym());

        $em->persist($subscription);
        $em->flush();

        return $this->json([
            'message'   => 'Abonnement créé avec succès',
            'client_id' => $client->getId(),
            'client'    => $client->getFirstName() . ' ' . $client->getLastName(),
            'type'      => $type->getName(),
            'price'     => $type->getPrice(),
            'status'    => $status,
            'startDate' => $startDate->format('d/m/Y'),
            'endDate'   => $endDate->format('d/m/Y'),
        ], 201);
    }

    // ── GET ALL ───────────────────────────────────────────────────────────
    #[Route('', methods: ['GET'])]
    public function index(SubscriptionRepository $repo): JsonResponse
    {
        $subscriptions = $repo->findAll();
        return $this->json(array_map(fn($sub) => $this->formatSubscription($sub), $subscriptions));
    }

    // ── GET ONE ───────────────────────────────────────────────────────────
    #[Route('/{id}', methods: ['GET'])]
    public function show(Subscription $subscription): JsonResponse
    {
        return $this->json($this->formatSubscription($subscription));
    }

    // ── GET BY CLIENT ─────────────────────────────────────────────────────
    #[Route('/client/{id}', methods: ['GET'])]
    public function byClient(int $id, SubscriptionRepository $repo): JsonResponse
    {
        $subscriptions = $repo->findBy(
            ['client' => $id],
            ['startDate' => 'DESC']   // ← historique du plus récent au plus ancien
        );
        return $this->json(array_map(fn($sub) => $this->formatSubscription($sub), $subscriptions));
    }

    // ── RENEW ─────────────────────────────────────────────────────────────
    #[Route('/{id}/renew', methods: ['POST'])]
    public function renew(Subscription $oldSubscription, EntityManagerInterface $em): JsonResponse
    {
        $type      = $oldSubscription->getSubscriptionType();
        $startDate = new \DateTime();
        $endDate   = (clone $startDate)->modify('+' . $type->getDurationDays() . ' days');

        $newSubscription = new Subscription();
        $newSubscription->setClient($oldSubscription->getClient());
        $newSubscription->setSubscriptionType($type);
        $newSubscription->setStartDate($startDate);
        $newSubscription->setEndDate($endDate);
        $newSubscription->setPrice($type->getPrice());
        $newSubscription->setStatus('actif');

        $em->persist($newSubscription);
        $em->flush();

        return $this->json([
            'message'             => 'Abonnement renouvelé',
            'new_subscription_id' => $newSubscription->getId(),
        ]);
    }

    // ── FORMAT ────────────────────────────────────────────────────────────
    private function formatSubscription(Subscription $sub): array
    {
        return [
            'id'        => $sub->getId(),
            'client_id' => $sub->getClient()->getId(),  // ← ajouté pour le groupement front
            'client'    => $sub->getClient()->getFirstName() . ' ' . $sub->getClient()->getLastName(),
            'type'      => $sub->getSubscriptionType()->getName(),
            'price'     => $sub->getPrice(),
            'startDate' => $sub->getStartDate()->format('d/m/Y'),
            'endDate'   => $sub->getEndDate()->format('d/m/Y'),
            'status'    => $this->calculateStatus($sub),
        ];
    }

    private function calculateStatus(Subscription $sub): string
    {
        return ($sub->getEndDate() >= new \DateTime()) ? 'Actif' : 'Expiré';
    }
}