<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users')]
class UserController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $users = $em->getRepository(User::class)->findAll();
        $data  = [];

        foreach ($users as $user) {
            // ← exclure les ROLE_CLIENT de la liste admin
            if (in_array('ROLE_CLIENT', $user->getRoles())) continue;

            $data[] = [
                "id"        => $user->getId(),
                "email"     => $user->getEmail(),
                "name"      => $user->getName(),
                "roles"     => $user->getRoles(),
                "createdAt" => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(User $user): JsonResponse
    {
        return new JsonResponse([
            "id"    => $user->getId(),
            "email" => $user->getEmail(),
            "name"  => $user->getName(),
            "roles" => $user->getRoles(),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        // ← protection admin
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['error' => 'Tous les champs sont obligatoires'], 400);
        }

        // vérifier si email déjà utilisé
        $existing = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return new JsonResponse(['error' => 'Cet email est déjà utilisé'], 409);
        }

        $user = new User();
        $user->setName($data["name"]);
        $user->setEmail($data["email"]);
        $user->setCreatedAt(new \DateTime());
        $user->setPassword($hasher->hashPassword($user, $data["password"]));

        // ← rôle valide uniquement ROLE_ADMIN ou ROLE_USER
        $role = $data["role"] ?? 'ROLE_USER';
        if (!in_array($role, ['ROLE_ADMIN', 'ROLE_USER'])) {
            $role = 'ROLE_USER';
        }
        $user->setRoles([$role]);

        $em->persist($user);
        $em->flush();

        return new JsonResponse(["message" => "Utilisateur créé"], 201);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(User $user, EntityManagerInterface $em): JsonResponse
    {
        // ← protection admin
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // empêcher la suppression de son propre compte
        if ($user === $this->getUser()) {
            return new JsonResponse(['error' => 'Impossible de supprimer votre propre compte'], 400);
        }

        $em->remove($user);
        $em->flush();

        return new JsonResponse(["message" => "Utilisateur supprimé"]);
    }
}