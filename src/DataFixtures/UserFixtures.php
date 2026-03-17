<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {

        // ADMIN
        $admin = new User();
        $admin->setEmail("admin@gym.com");
        $admin->setName("Admin");
        $admin->setRoles(["ROLE_ADMIN"]);
        $admin->setCreatedAt(new \DateTime());

        $hashedPassword = $this->passwordHasher->hashPassword(
            $admin,
            "123456"
        );

        $admin->setPassword($hashedPassword);

        $manager->persist($admin);


        // COACH
        $coach = new User();
        $coach->setEmail("coach@gym.com");
        $coach->setName("Coach");
        $coach->setRoles(["ROLE_COACH"]);
        $coach->setCreatedAt(new \DateTime());

        $hashedPassword = $this->passwordHasher->hashPassword(
            $coach,
            "123456"
        );

        $coach->setPassword($hashedPassword);

        $manager->persist($coach);


        // RECEPTION
        $reception = new User();
        $reception->setEmail("reception@gym.com");
        $reception->setName("Reception");
        $reception->setRoles(["ROLE_RECEPTION"]);
        $reception->setCreatedAt(new \DateTime());

        $hashedPassword = $this->passwordHasher->hashPassword(
            $reception,
            "123456"
        );

        $reception->setPassword($hashedPassword);

        $manager->persist($reception);


        $manager->flush();
    }
}