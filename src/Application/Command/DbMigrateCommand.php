<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Infrastructure\Database\PdoConnectionFactory;
use RentReceiptCli\Infrastructure\Database\SqliteMigrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DbMigrateCommand extends Command
{
    protected static $defaultName = 'db:migrate';
    protected static $defaultDescription = 'Apply pending database migrations';

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show pending migrations without applying them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');

        $config = require __DIR__ . '/../../../config/config.php';
        $dbPath = (string) ($config['paths']['database'] ?? '');
        $root = (string) ($config['paths']['root'] ?? dirname(__DIR__, 3));
        $migrationsDir = $root . '/database/migrations';

        $pdo = (new PdoConnectionFactory($dbPath))->create();
        $migrator = new SqliteMigrator($pdo, $migrationsDir);

        $pending = $migrator->listPendingMigrations();

        if ($dryRun) {
            if (count($pending) === 0) {
                $output->writeln('<info>No pending migrations.</info>');
                return Command::SUCCESS;
            }

            $output->writeln('<comment>Pending migrations (dry-run):</comment>');
            foreach ($pending as $v) {
                $output->writeln(' - ' . $v);
            }
            return Command::SUCCESS;
        }

        if (count($pending) === 0) {
            $output->writeln('<info>No pending migrations.</info>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Applying %d migration(s)...</info>', count($pending)));

        $appliedCount = 0;
        foreach ($pending as $v) {
            $output->writeln(' + ' . $v);
            $migrator->apply($v);
            $appliedCount++;
        }

        $output->writeln(sprintf('<info>Done.</info> Applied: %d', $appliedCount));
        return Command::SUCCESS;
    }
}
