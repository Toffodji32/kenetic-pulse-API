<?php

namespace App\Controller\Api;

use App\Entity\Gym;
use App\Entity\GymOwner;
use App\Entity\GymSubscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/gym')]
class GymRegistrationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private JWTTokenManagerInterface $jwtManager,
    ) {}

    #[Route('/register', name: 'api_gym_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $gymName = $data['gymName'] ?? null;
        $ownerName = $data['ownerName'] ?? null;
        $ownerEmail = $data['ownerEmail'] ?? null;
        $ownerPassword = $data['ownerPassword'] ?? null;
        $ownerPhone = $data['ownerPhone'] ?? null;

        if (!$gymName || !$ownerName || !$ownerEmail || !$ownerPassword) {
            return new JsonResponse(['error' => 'gymName, ownerName, ownerEmail et ownerPassword requis'], 400);
        }

        $existingOwner = $this->em->getRepository(GymOwner::class)
            ->findOneByEmail($ownerEmail);

        if ($existingOwner) {
            return new JsonResponse(['error' => 'Cet email est déjà utilisé'], 409);
        }

        $existingUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $ownerEmail]);

        if ($existingUser) {
            return new JsonResponse(['error' => 'Cet email est déjà utilisé'], 409);
        }

        $this->em->beginTransaction();

        try {
            $owner = new GymOwner();
            $owner->setName($ownerName);
            $owner->setEmail($ownerEmail);
            $owner->setPassword($this->hasher->hashPassword($owner, $ownerPassword));
            $owner->setPhone($ownerPhone);
            $this->em->persist($owner);

            $slug = $this->generateUniqueSlug($gymName);

            $gym = new Gym();
            $gym->setName($gymName);
            $gym->setSlug($slug);
            $gym->setGymOwner($owner);
            $this->em->persist($gym);

            $trialEndsAt = (new \DateTime())->modify('+7 days');

            $subscription = new GymSubscription();
            $subscription->setGym($gym);
            $subscription->setStatus(GymSubscription::STATUS_TRIAL);
            $subscription->setPlan('monthly');
            $subscription->setPlanType('premium');
            $subscription->setTrialEndsAt($trialEndsAt);
            $subscription->setAmount(15000);
            $this->em->persist($subscription);

            $user = new User();
            $user->setEmail($ownerEmail);
            $user->setName($ownerName);
            $user->setPassword($this->hasher->hashPassword($user, $ownerPassword));
            $user->setRoles(['ROLE_ADMIN']);
            $user->setCreatedAt(new \DateTime());
            $user->setGym($gym);
            $this->em->persist($user);

            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();

            return new JsonResponse(['error' => 'Erreur lors de l\'inscription : ' . $e->getMessage()], 500);
        }

        $token = $this->jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
            ],
            'gym' => [
                'id' => $gym->getId(),
                'name' => $gym->getName(),
                'slug' => $gym->getSlug(),
                'subscriptionStatus' => GymSubscription::STATUS_TRIAL,
                'trialEndsAt' => $trialEndsAt->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-'));
        $baseSlug = $slug;
        $counter = 1;

        while ($this->em->getRepository(Gym::class)->findOneBySlug($slug)) {
            $slug = $baseSlug . '-' . ++$counter;
        }

        return $slug;
    }
}
