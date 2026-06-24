<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\GymRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-super-admin', description: 'Crée ou promeut un utilisateur super admin')]
class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private GymRepository $gymRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email du super admin')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Mot de passe')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Nom du super admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $email = $input->getOption('email');
        if (!$email) {
            $email = $helper->ask($input, $output, new Question('Email : ', 'superadmin@kinetic-pulse.com'));
        }

        $name = $input->getOption('name');
        if (!$name) {
            $name = $helper->ask($input, $output, new Question('Nom : ', 'Super Admin'));
        }

        $password = $input->getOption('password');
        if (!$password) {
            $passwordQuestion = new Question('Mot de passe : ');
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $passwordQuestion);
            if (!$password) {
                $password = 'SuperAdmin@2026';
                $output->writeln('<comment>Mot de passe par défaut: SuperAdmin@2026</comment>');
            }
        }

        // Check if user already exists
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $roles = $existing->getRoles();
            if (in_array('ROLE_SUPER_ADMIN', $roles, true)) {
                $output->writeln("<info>L'utilisateur $email est déjà super admin.</info>");
                return Command::SUCCESS;
            }
            $roles[] = 'ROLE_SUPER_ADMIN';
            $existing->setRoles($roles);
            $this->em->flush();
            $output->writeln("<info>Utilisateur $email promu super admin.</info>");
            return Command::SUCCESS;
        }

        // Find a gym to associate (use first available)
        $gym = $this->gymRepo->findOneBy([]);
        if (!$gym) {
            $output->writeln('<error>Aucune salle trouvée. Créez d\'abord une salle.</error>');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']);
        $user->setGym($gym);
        $user->setCreatedAt(new \DateTime());
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln("<info>Super admin créé : $email</info>");
        return Command::SUCCESS;
    }
}
