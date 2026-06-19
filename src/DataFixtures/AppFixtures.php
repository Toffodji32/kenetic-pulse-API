<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Gym;
use App\Entity\GymOwner;
use App\Entity\GymSubscription;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ── Default GymOwner ──────────────────────────
        $owner = new GymOwner();
        $owner->setName('Kinetic Pulse Default');
        $owner->setEmail('admin@kineticpulse.com');
        $owner->setPassword($this->hasher->hashPassword($owner, 'ChangeMe2026!'));
        $owner->setCreatedAt(new \DateTime());
        $manager->persist($owner);

        // ── Default Gym ───────────────────────────────
        $gym = new Gym();
        $gym->setName('Kinetic Pulse');
        $gym->setSlug('kinetic-pulse-default');
        $gym->setGymOwner($owner);
        $gym->setCreatedAt(new \DateTime());
        $manager->persist($gym);

        // ── Default GymSubscription ───────────────────
        $subscription = new GymSubscription();
        $subscription->setGym($gym);
        $subscription->setStatus(GymSubscription::STATUS_ACTIVE);
        $subscription->setPlan('monthly');
        $subscription->setTrialEndsAt((new \DateTime())->modify('+7 days'));
        $subscription->setStartsAt(new \DateTime());
        $subscription->setEndsAt((new \DateTime())->modify('+1 year'));
        $subscription->setAmount(15000);
        $manager->persist($subscription);

        // ── Rôles ─────────────────────────────────────
        $rolesData = [
            ['name' => 'ROLE_ADMIN',  'label' => 'Administrateur'],
            ['name' => 'ROLE_USER',   'label' => 'Réceptionniste'],
            ['name' => 'ROLE_CLIENT', 'label' => 'Client'],
        ];

        foreach ($rolesData as $roleData) {
            $role = new Role();
            $role->setName($roleData['name']);
            $role->setLabel($roleData['label']);
            $manager->persist($role);
            $this->addReference('role-' . $roleData['name'], $role);
        }

        // ── Catégories produits ───────────────────────
        $categoriesData = [
            ['name' => 'Suppléments / protéines',  'description' => 'Whey, créatine, BCAA et compléments alimentaires sportifs'],
            ['name' => 'Équipements sportifs',      'description' => 'Haltères, barres, bancs et machines de musculation'],
            ['name' => 'Vêtements de sport',        'description' => 'T-shirts, shorts, leggings et tenues de sport'],
            ['name' => 'Boissons énergétiques',     'description' => 'Pre-workout, boissons isotoniques et récupération'],
            ['name' => 'Accessoires fitness',       'description' => 'Gants, sangles, ceintures et accessoires d\'entraînement'],
            ['name' => 'Autre',                     'description' => 'Autres produits liés au fitness et au bien-être'],
        ];

        foreach ($categoriesData as $catData) {
            $category = new Category();
            $category->setName($catData['name']);
            $category->setDescription($catData['description']);
            $category->setGym($gym);
            $manager->persist($category);
            $this->addReference('category-' . $catData['name'], $category);
        }

        // ── Utilisateurs ──────────────────────────────
        $usersData = [
            [
                'name'     => 'Admin Kinetic',
                'email'    => 'admin@kinetic.com',
                'password' => 'Admin@2024',
                'roles'    => ['ROLE_ADMIN'],
            ],
            [
                'name'     => 'Receptionniste',
                'email'    => 'reception@kinetic.com',
                'password' => 'Reception@2024',
                'roles'    => ['ROLE_USER'],
            ],
        ];

        foreach ($usersData as $userData) {
            $user = new User();
            $user->setName($userData['name']);
            $user->setEmail($userData['email']);
            $user->setRoles($userData['roles']);
            $user->setCreatedAt(new \DateTime());
            $user->setGym($gym);
            $user->setPassword(
                $this->hasher->hashPassword($user, $userData['password'])
            );
            $manager->persist($user);
        }

        $manager->flush();
    }
}
