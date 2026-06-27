<?php

namespace App\Command;

use App\Entity\SubscriptionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed-subscription-types', description: 'Ajoute les types d\'abonnement mensuel et annuel')]
class SeedSubscriptionTypesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $types = [
            ['name' => 'Mensuel', 'price' => 15000, 'durationDays' => 30],
            ['name' => 'Annuel', 'price' => 150000, 'durationDays' => 365],
        ];

        $repo = $this->em->getRepository(SubscriptionType::class);

        foreach ($types as $data) {
            $existing = $repo->findOneBy(['name' => $data['name']]);
            if ($existing) {
                $output->writeln("<comment>Type '{$data['name']}' existe déjà.</comment>");
                continue;
            }

            $type = new SubscriptionType();
            $type->setName($data['name']);
            $type->setPrice($data['price']);
            $type->setDurationDays($data['durationDays']);
            $this->em->persist($type);
            $output->writeln("<info>Type '{$data['name']}' créé.</info>");
        }

        $this->em->flush();
        return Command::SUCCESS;
    }
}
