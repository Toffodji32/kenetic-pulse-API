<?php

namespace App\Controller\Api;

use App\Entity\SubscriptionType;
use App\Repository\SubscriptionTypeRepository;
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
            $data[] = [
                "id" => $type->getId(),
                "name" => $type->getName(),
                "price" => $type->getPrice(),
                "durationDays" => $type->getDurationDays()
            ];
        }

        return $this->json($data);
    }


    #[Route('/{id}', methods: ['GET'])]
    public function show(SubscriptionType $type): JsonResponse
    {
        return $this->json([
            "id" => $type->getId(),
            "name" => $type->getName(),
            "price" => $type->getPrice(),
            "durationDays" => $type->getDurationDays()
        ]);
    }


    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'], $data['price'], $data['durationDays'])) {
            return $this->json([
                "error" => "name, price et durationDays requis"
            ], 400);
        }

        $type = new SubscriptionType();
        $type->setName($data['name']);
        $type->setPrice($data['price']);
        $type->setDurationDays($data['durationDays']);

        $em->persist($type);
        $em->flush();

        return $this->json([
            "message" => "Type d'abonnement créé",
            "id" => $type->getId()
        ], 201);
    }


    #[Route('/{id}', methods: ['PUT'])]
    public function update(SubscriptionType $type, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $type->setName($data['name']);
        }

        if (isset($data['price'])) {
            $type->setPrice($data['price']);
        }

        if (isset($data['durationDays'])) {
            $type->setDurationDays($data['durationDays']);
        }

        $em->flush();

        return $this->json([
            "message" => "Type d'abonnement modifié"
        ]);
    }


    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(SubscriptionType $type, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($type);
        $em->flush();

        return $this->json([
            "message" => "Type d'abonnement supprimé"
        ]);
    }
}