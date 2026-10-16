<?php

declare(strict_types=1);

namespace PayKit\Bundle\Command;

use PayKit\Bundle\Model\SyncResult;
use PayKit\Bundle\Provider\PaymentProviderInterface;
use PayKit\Bundle\Service\ProductSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'paykit:status', description: 'Validate the PayKit setup: database schema and plan sync state')]
final class StatusCommand extends Command
{
    public function __construct(
        private readonly ProductSyncService $sync,
        private readonly PaymentProviderInterface $provider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('throw', null, InputOption::VALUE_NONE, 'Exit with code 1 when something needs attention (for CI)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln(\sprintf('Provider: <info>%s</info>', $this->provider->getId()));

        try {
            $results = $this->sync->sync(true);
        } catch (\Throwable $e) {
            $io->error('Database check failed (did you run the migrations?): '.$e->getMessage());

            return $input->getOption('throw') ? Command::FAILURE : Command::SUCCESS;
        }

        $pending = array_values(array_filter($results, static fn (SyncResult $r): bool => SyncResult::UNCHANGED !== $r->action));
        if ([] === $pending) {
            $io->success('All plans are in sync.');

            return Command::SUCCESS;
        }
        $io->warning(\sprintf('Plans out of sync: %s. Run "bin/console paykit:push".', implode(', ', array_map(static fn (SyncResult $r): string => $r->planId, $pending))));

        return $input->getOption('throw') ? Command::FAILURE : Command::SUCCESS;
    }
}
