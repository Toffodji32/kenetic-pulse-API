<?php

namespace App\Controller\Api;

use App\Entity\SubscriptionType;
use App\Repository\SubscriptionTypeRepository;
use App\Security\GymResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/subscription-types')]
class SubscriptionTypeController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(SubscriptionTypeRepository $repo): JsonResponse
    {
        $types = $repo->findAll();

        $data = [];

        foreach ($types as $type) {
            $data[] = $this->format($type);
        }

        return $this->json($data);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(SubscriptionType $type): JsonResponse
    {
        return $this->json($this->format($type));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        GymResolver $gymResolver,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'], $data['price'], $data['durationDays'])) {
            return $this->json(["error" => "name, price et durationDays requis"], 400);
        }

        $gym = $gymResolver->getGym();
        if (!$gym) {
            return $this->json(["error" => "Aucune salle associée"], 403);
        }

        $type = new SubscriptionType();
        $type->setName($data['name']);
        $type->setPrice((float) $data['price']);
        $type->setDurationDays((int) $data['durationDays']);
        $type->setGym($gym);

        $em->persist($type);
        $em->flush();

        return $this->json($this->format($type), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        SubscriptionType $type,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $type->setName($data['name']);
        }

        if (isset($data['price'])) {
            $type->setPrice((float) $data['price']);
        }

        if (isset($data['durationDays'])) {
            $type->setDurationDays((int) $data['durationDays']);
        }

        $em->flush();

        return $this->json($this->format($type));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(
        SubscriptionType $type,
        EntityManagerInterface $em,
    ): JsonResponse {
        $em->remove($type);
        $em->flush();

        return $this->json(["message" => "Type d'abonnement supprimé"]);
    }

    private function format(SubscriptionType $type): array
    {
        return [
            "id" => $type->getId(),
            "name" => $type->getName(),
            "price" => $type->getPrice(),
            "durationDays" => $type->getDurationDays(),
        ];
    }
}
