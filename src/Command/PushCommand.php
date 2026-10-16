<?php

declare(strict_types=1);

namespace PayKit\Bundle\Command;

use PayKit\Bundle\Model\SyncResult;
use PayKit\Bundle\Service\ProductSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'paykit:push', description: 'Sync configured features and plans to the database and the payment provider')]
final class PushCommand extends Command
{
    public function __construct(private readonly ProductSyncService $sync)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing anything')
            ->setHelp(<<<'HELP'
                Run after changing <info>paykit.plans</info> and in your deploy pipeline (after migrations):

                  <info>php bin/console doctrine:migrations:migrate -n && php bin/console paykit:push</info>

                Price or feature changes create a new plan version; existing subscribers keep theirs.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $results = $this->sync->sync($dryRun);

        $io->table(['Plan', 'Action', 'Version'], array_map(static fn (SyncResult $r): array => [$r->planId, $r->action, $r->version], $results));
        $changed = \count(array_filter($results, static fn (SyncResult $r): bool => SyncResult::UNCHANGED !== $r->action));
        $io->success($dryRun ? \sprintf('Dry run: %d plan(s) would change.', $changed) : \sprintf('%d plan(s) synced.', $changed));

        return Command::SUCCESS;
    }
}
