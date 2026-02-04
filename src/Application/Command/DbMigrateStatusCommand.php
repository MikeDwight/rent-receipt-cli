<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Infrastructure\Database\PdoConnectionFactory;
use RentReceiptCli\Infrastructure\Database\SqliteMigrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbMigrateStatusCommand extends Command
{
    protected static $defaultName = 'db:migrate:status';
    protected static $defaultDescription = 'Show migrations status (applied / pending)';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = require __DIR__ . '/../../../config/config.php';
        $dbPath = (string) ($config['paths']['database'] ?? '');
        $root = (string) ($config['paths']['root'] ?? dirname(__DIR__, 3));
        $migrationsDir = $root . '/database/migrations';

        $pdo = (new PdoConnectionFactory($dbPath))->create();
        $migrator = new SqliteMigrator($pdo, $migrationsDir);

        $all = $migrator->listAllMigrations();
        $applied = $migrator->listAppliedMigrations();

        $output->writeln(sprintf('Migrations dir: <info>%s</info>', $migrationsDir));
        $output->writeln(sprintf('Total: <info>%d</info> | Applied: <info>%d</info> | Pending: <comment>%d</comment>',
            count($all),
            count($applied),
            count($all) - count($applied)
        ));

        $output->writeln('');
        $output->writeln('<info>Applied</info>');
        if (count($applied) === 0) {
            $output->writeln(' (none)');
        } else {
            foreach ($all as $v) {
                if (isset($applied[$v])) {
                    $output->writeln(sprintf(' + %s  <comment>%s</comment>', $v, $applied[$v]));
                }
            }
        }

        $output->writeln('');
        $output->writeln('<comment>Pending</comment>');
        $pending = array_values(array_filter($all, static fn (string $v): bool => !isset($applied[$v])));
        if (count($pending) === 0) {
            $output->writeln(' (none)');
        } else {
            foreach ($pending as $v) {
                $output->writeln(' - ' . $v);
            }
        }

        return Command::SUCCESS;
    }
}
