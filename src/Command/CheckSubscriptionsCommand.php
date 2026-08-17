<?php
namespace App\Command;

use App\Service\SubscriptionCheckService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-subscriptions',
    description: 'Vérifie les abonnements et notifie les clients et les propriétaires de salle',
)]
class CheckSubscriptionsCommand extends Command
{
    public function __construct(
        private SubscriptionCheckService $checkService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait envoyé sans envoyer d\'email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Vérification des abonnements — ' . date('d/m/Y H:i') . ($dryRun ? ' [DRY RUN]' : ''));

        $result = $this->checkService->run($dryRun);

        $io->section(sprintf('%d abonnement(s) expiré(s)', count($result['expired'])));
        foreach ($result['expired'] as $r) {
            $io->text(sprintf('  [EXPIRÉ] %s → %s', $r['client'], $r['email']));
        }

        $io->section(sprintf('%d rappel(s) client', count($result['reminders'])));
        foreach ($result['reminders'] as $r) {
            $io->text(sprintf('  [RAPPEL J-%d] %s → %s (expire le %s)', $r['days_left'], $r['client'], $r['email'], $r['expires_on']));
        }

        $io->section(sprintf('%d récap(s) propriétaire', count($result['owner_summaries'])));
        foreach ($result['owner_summaries'] as $r) {
            $io->text(sprintf('  [SALLE] %s (%s) → %s : %d abonnement(s)', $r['gym'], $r['gym_id'], $r['owner_email'], $r['subscriptions']));
        }

        $io->success('Vérification terminée.' . ($dryRun ? ' (aucun email envoyé)' : ''));
        return Command::SUCCESS;
    }
}
