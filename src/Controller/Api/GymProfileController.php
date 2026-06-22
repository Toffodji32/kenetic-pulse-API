<?php

namespace App\Controller\Api;

use App\Security\GymResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/gym')]
#[IsGranted('ROLE_ADMIN')]
class GymProfileController extends AbstractController
{
    public function __construct(
        private GymResolver $gymResolver,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/profile', name: 'api_gym_profile_get', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        $gym = $this->gymResolver->getGym();

        if (!$gym) {
            return new JsonResponse(['error' => 'Gym non trouvée'], 404);
        }

        return $this->json([
            'name' => $gym->getName(),
            'email' => $gym->getEmail(),
            'phone' => $gym->getPhone(),
            'address' => $gym->getAddress(),
            'description' => $gym->getDescription(),
            'logo' => $gym->getLogo(),
        ]);
    }

    #[Route('/profile', name: 'api_gym_profile_update', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $gym = $this->gymResolver->getGym();

        if (!$gym) {
            return new JsonResponse(['error' => 'Gym non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $gym->setName($data['name']);
        if (isset($data['email'])) $gym->setEmail($data['email']);
        if (isset($data['phone'])) $gym->setPhone($data['phone']);
        if (isset($data['address'])) $gym->setAddress($data['address']);
        if (isset($data['description'])) $gym->setDescription($data['description']);

        $this->em->flush();

        return $this->json([
            'name' => $gym->getName(),
            'email' => $gym->getEmail(),
            'phone' => $gym->getPhone(),
            'address' => $gym->getAddress(),
            'description' => $gym->getDescription(),
            'logo' => $gym->getLogo(),
        ]);
    }

    #[Route('/logo', name: 'api_gym_logo_upload', methods: ['POST'])]
    public function uploadLogo(Request $request): JsonResponse
    {
        $gym = $this->gymResolver->getGym();

        if (!$gym) {
            return new JsonResponse(['error' => 'Gym non trouvée'], 404);
        }

        $file = $request->files->get('logo');

        if (!$file) {
            return new JsonResponse(['error' => 'Fichier logo requis'], 400);
        }

        $mimeType = $file->getMimeType();
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (!in_array($mimeType, $allowed)) {
            return new JsonResponse(['error' => 'Format non autorisé (jpeg, png, webp, gif)'], 400);
        }

        $base64 = base64_encode(file_get_contents($file->getPathname()));
        $dataUrl = sprintf('data:%s;base64,%s', $mimeType, $base64);

        $gym->setLogo($dataUrl);
        $this->em->flush();

        return $this->json(['logo' => $gym->getLogo()]);
    }
}
