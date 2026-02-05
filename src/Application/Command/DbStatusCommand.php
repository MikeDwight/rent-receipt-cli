<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Infrastructure\Database\PdoConnectionFactory;
use RentReceiptCli\Infrastructure\Database\SqliteMigrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbStatusCommand extends Command
{
    protected static $defaultName = 'db:status';
    protected static $defaultDescription = 'Show database status and basic statistics';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = require __DIR__ . '/../../../config/config.php';

        $dbPath = (string) ($config['paths']['database'] ?? '');
        if ($dbPath === '' || !file_exists($dbPath)) {
            $output->writeln('<error>Database file not found.</error>');
            $output->writeln("Path: {$dbPath}");
            return Command::FAILURE;
        }

        $pdo = (new PdoConnectionFactory($dbPath))->create();

        $output->writeln('<info>Database</info>');
        $output->writeln(sprintf('  Path: %s', $dbPath));
        $output->writeln(sprintf('  Size: %d KB', (int) ceil(filesize($dbPath) / 1024)));
        $output->writeln('');

        // Migrations
        $root = (string) ($config['paths']['root'] ?? dirname(__DIR__, 3));
        $migrationsDir = $root . '/database/migrations';

        $migrator = new SqliteMigrator($pdo, $migrationsDir);
        $applied = $migrator->listAppliedMigrations();
        $all = $migrator->listAllMigrations();

        $output->writeln('<info>Migrations</info>');
        $output->writeln(sprintf('  Directory: %s', $migrationsDir));
        $output->writeln(sprintf('  Applied: %d', count($applied)));
        $output->writeln(sprintf('  Pending: %d', count($all) - count($applied)));
        $output->writeln('');

        // Tables counts
        $tables = [
            'owners',
            'properties',
            'tenants',
            'rent_payments',
            'receipts',
        ];

        $output->writeln('<info>Tables</info>');
        foreach ($tables as $table) {
            $existsStmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = :name LIMIT 1;");
            $existsStmt->execute([':name' => $table]);
            $exists = $existsStmt->fetchColumn() !== false;

            if (!$exists) {
                $output->writeln(sprintf('  %-13s %s', $table, '<comment>missing</comment>'));
                continue;
            }

            $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
            $count = $stmt !== false ? (int) $stmt->fetchColumn() : 0;
            $output->writeln(sprintf('  %-13s %d', $table, $count));
        }


        return Command::SUCCESS;
    }
}
