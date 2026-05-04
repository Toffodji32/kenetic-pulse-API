<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // ── Rôles ────────────────────────────────────
        $rolesData = [
            ['name' => 'ROLE_ADMIN',  'label' => 'Administrateur'],
            ['name' => 'ROLE_USER',   'label' => 'Réceptionniste'],
            ['name' => 'ROLE_CLIENT', 'label' => 'Client '],
        ];

        foreach ($rolesData as $roleData) {
            $role = new Role();
            $role->setName($roleData['name']);
            $role->setLabel($roleData['label']);
            $manager->persist($role);
            // on garde une référence pour pouvoir les utiliser ailleurs
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
            $manager->persist($category);
            $this->addReference('category-' . $catData['name'], $category);
        }

        $manager->flush();
    }
}