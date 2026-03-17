<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Subscription;
use App\Entity\SubscriptionType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SubscriptionFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // ================================
        // 🔹 TYPES D’ABONNEMENTS
        // ================================

        $mensuel = new SubscriptionType();
        $mensuel->setName('Mensuel');
        $mensuel->setDurationDays(30);
        $mensuel->setPrice(10000);

        $manager->persist($mensuel);

        $annuel = new SubscriptionType();
        $annuel->setName('Annuel');
        $annuel->setDurationDays(365);
        $annuel->setPrice(100000);

        $manager->persist($annuel);

        // ================================
        // 🔹 CLIENT 1 (ACTIF)
        // ================================

        $client1 = new Client();
        $client1->setFirstName('Jean');
        $client1->setLastName('Michel');
        $client1->setEmail('jean@test.com');
        $client1->setPhone('97000005');
        $client1->setRegistrationDate(new \DateTime());
        $client1->setUuid('uuid-client-actif');

        $manager->persist($client1);

        // abonnement actif
        $sub1 = new Subscription();
        $sub1->setClient($client1);
        $sub1->setSubscriptionType($mensuel);

        $sub1->setStartDate(new \DateTime());
        $sub1->setEndDate((new \DateTime())->modify('+30 days'));
        $sub1->setPrice(10000);
        $sub1->setStatus('active');

        $manager->persist($sub1);

        // ================================
        // 🔹 CLIENT 2 (EXPIRÉ)
        // ================================

        $client2 = new Client();
        $client2->setFirstName('Paul');
        $client2->setLastName('Martin');
        $client2->setEmail('paul@test.com');
        $client2->setPhone('96000000');
        $client2->setRegistrationDate(new \DateTime());
        $client2->setUuid('uuid-client-expire');

        $manager->persist($client2);

        // abonnement expiré
        $sub2 = new Subscription();
        $sub2->setClient($client2);
        $sub2->setSubscriptionType($mensuel);

        $sub2->setStartDate((new \DateTime())->modify('-60 days'));
        $sub2->setEndDate((new \DateTime())->modify('-30 days'));
        $sub2->setPrice(10000);
        $sub2->setStatus('expired');

        $manager->persist($sub2);

        // ================================
        // 🔹 CLIENT 3 (SANS ABONNEMENT)
        // ================================

        $client3 = new Client();
        $client3->setFirstName('Alice');
        $client3->setLastName('Koffi');
        $client3->setEmail('alice@test.com');
        $client3->setPhone('95000000');
        $client3->setRegistrationDate(new \DateTime());
        $client3->setUuid('uuid-client-no-sub');

        $manager->persist($client3);

        // ================================
        // 🔹 CLIENT 4 (ANNUEL ACTIF)
        // ================================

        $client4 = new Client();
        $client4->setFirstName('Marc');
        $client4->setLastName('Doe');
        $client4->setEmail('marc@test.com');
        $client4->setPhone('94000000');
        $client4->setRegistrationDate(new \DateTime());
        $client4->setUuid('uuid-client-annuel');

        $manager->persist($client4);

        $sub4 = new Subscription();
        $sub4->setClient($client4);
        $sub4->setSubscriptionType($annuel);

        $sub4->setStartDate(new \DateTime());
        $sub4->setEndDate((new \DateTime())->modify('+365 days'));
        $sub4->setPrice(100000);
        $sub4->setStatus('active');

        $manager->persist($sub4);

        // ================================
        // 💾 SAVE
        // ================================

        $manager->flush();
    }
}