<?php

namespace App\Controller\Api;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/roles')]
class RoleController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(RoleRepository $repo): JsonResponse
    {
        return $this->json(array_map(fn($r) => [
            'id'    => $r->getId(),
            'name'  => $r->getName(),
            'label' => $r->getLabel(),
        ], $repo->findAll()));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['label'])) {
            return $this->json(['error' => 'name et label obligatoires'], 400);
        }

        $role = new Role();
        $role->setName(strtoupper($data['name']));
        $role->setLabel($data['label']);
        $em->persist($role);
        $em->flush();

        return $this->json(['message' => 'Rôle créé', 'id' => $role->getId()], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(Role $role, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = json_decode($request->getContent(), true);

        if (isset($data['label'])) $role->setLabel($data['label']);
        $em->flush();

        return $this->json(['message' => 'Rôle modifié']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Role $role, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $em->remove($role);
        $em->flush();

        return $this->json(['message' => 'Rôle supprimé']);
    }

    // ✅ ASSIGNER UN RÔLE À UN UTILISATEUR
    #[Route('/{roleId}/assign-user/{userId}', methods: ['POST'])]
    public function assignRoleToUser(
        int $roleId,
        int $userId,
        RoleRepository $roleRepository,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $role = $roleRepository->find($roleId);
        if (!$role) {
            return $this->json(['error' => 'Rôle non trouvé'], 404);
        }

        $user = $userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Assigner le rôle à l'utilisateur
        $user->setRole($role);
        $em->flush();

        return $this->json([
            'message' => 'Rôle assigné avec succès',
            'userId' => $user->getId(),
            'role' => [
                'id' => $role->getId(),
                'name' => $role->getName(),
                'label' => $role->getLabel(),
            ]
        ], 200);
    }

    // ✅ RETIRER UN RÔLE À UN UTILISATEUR
    #[Route('/{userId}/remove-role', methods: ['POST'])]
    public function removeRoleFromUser(
        int $userId,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $user->setRole(null);
        $em->flush();

        return $this->json(['message' => 'Rôle retiré avec succès'], 200);
    }
}