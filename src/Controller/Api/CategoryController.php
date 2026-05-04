<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/categories')]
class CategoryController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(CategoryRepository $repo): JsonResponse
    {
        return $this->json(array_map(fn($c) => [
            'id'          => $c->getId(),
            'name'        => $c->getName(),
            'description' => $c->getDescription(),
        ], $repo->findAll()));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->json(['error' => 'Nom obligatoire'], 400);
        }

        // vérifier si la catégorie existe déjà
        $existing = $em->getRepository(Category::class)->findOneBy(['name' => $data['name']]);
        if ($existing) {
            return $this->json(['error' => 'Cette catégorie existe déjà'], 409);
        }

        $category = new Category();
        $category->setName($data['name']);
        $category->setDescription($data['description'] ?? null);
        $em->persist($category);
        $em->flush();

        return $this->json(['message' => 'Catégorie créée', 'id' => $category->getId()], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(Category $category, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = json_decode($request->getContent(), true);

        if (isset($data['name']))        $category->setName($data['name']);
        if (isset($data['description'])) $category->setDescription($data['description']);
        $em->flush();

        return $this->json(['message' => 'Catégorie modifiée']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Category $category, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // vérifier si des produits utilisent cette catégorie
        if (!$category->getProducts()->isEmpty()) {
            return $this->json([
                'error' => 'Impossible de supprimer — des produits utilisent cette catégorie'
            ], 400);
        }

        $em->remove($category);
        $em->flush();

        return $this->json(['message' => 'Catégorie supprimée']);
    }
}