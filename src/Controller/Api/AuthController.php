<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class AuthController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;
    private JWTTokenManagerInterface $jwtManager;
    private RefreshTokenGeneratorInterface $refreshTokenGenerator;
    private RefreshTokenManagerInterface $refreshTokenManager;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager
    ) {
        $this->em = $em;
        $this->hasher = $hasher;
        $this->jwtManager = $jwtManager;
        $this->refreshTokenGenerator = $refreshTokenGenerator;
        $this->refreshTokenManager = $refreshTokenManager;
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'])) {
            return new JsonResponse(['error' => 'Email et mot de passe requis'], 400);
        }

        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if (!$user || !$this->hasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Identifiants incorrects'], 401);
        }

        $token = $this->jwtManager->create($user);
        $refreshToken = $this->createRefreshToken($user);

        $gym = $user->getGym();

        return new JsonResponse([
            'token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => 1800,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
                'gym_slug' => $gym?->getSlug(),
                'gym_name' => $gym?->getName(),
            ]
        ]);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $gym = $user->getGym();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
            'gym_slug' => $gym?->getSlug(),
            'gym_name' => $gym?->getName(),
        ]);
    }

    private function createRefreshToken(User $user): string
    {
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, 1800);
        $this->refreshTokenManager->save($refreshToken);

        return $refreshToken->getRefreshToken();
    }
}