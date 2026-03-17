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

    #[Route('', methods:['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $users = $em->getRepository(User::class)->findAll();

        $data = [];

        foreach ($users as $user) {
            $data[] = [
                "id"=>$user->getId(),
                "email"=>$user->getEmail(),
                "name"=>$user->getName(),
                "roles"=>$user->getRoles(),
                "createdAt"=>$user->getCreatedAt()
            ];
        }

        return new JsonResponse($data);
    }


    #[Route('/{id}', methods:['GET'])]
    public function show(User $user): JsonResponse
    {
        return new JsonResponse([
            "id"=>$user->getId(),
            "email"=>$user->getEmail(),
            "name"=>$user->getName(),
            "roles"=>$user->getRoles()
        ]);
    }


    #[Route('', methods:['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {

        $data=json_decode($request->getContent(),true);

        $user=new User();

        $user->setName($data["name"]);
        $user->setEmail($data["email"]);
        $user->setRoles([$data["role"]]);
        $user->setCreatedAt(new \DateTime());

        $user->setPassword(
            $hasher->hashPassword($user,$data["password"])
        );

        $em->persist($user);
        $em->flush();

        return new JsonResponse(["message"=>"Utilisateur créé"],201);
    }


    #[Route('/{id}', methods:['DELETE'])]
    public function delete(User $user, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($user);
        $em->flush();

        return new JsonResponse(["message"=>"Utilisateur supprimé"]);
    }

}