<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Infrastructure\Database\PdoConnectionFactory;
use RentReceiptCli\Infrastructure\Database\SqliteMigrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DbMigrateMarkCommand extends Command
{
    protected static $defaultName = 'db:migrate:mark';
    protected static $defaultDescription = 'Mark a migration as applied without executing it';

    protected function configure(): void
    {
        $this
            ->addArgument('version', InputArgument::REQUIRED, 'Migration filename to mark (e.g. 2026_02_03_000001_xxx.sql)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite applied_at if already marked')
            ->addOption('applied-at', null, InputOption::VALUE_REQUIRED, 'Override applied_at (SQLite datetime text)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = (string) $input->getArgument('version');
        $force = (bool) $input->getOption('force');
        $appliedAt = $input->getOption('applied-at');
        $appliedAt = $appliedAt !== null ? (string) $appliedAt : null;

        $config = require __DIR__ . '/../../../config/config.php';
        $dbPath = (string) ($config['paths']['database'] ?? '');
        $root = (string) ($config['paths']['root'] ?? dirname(__DIR__, 3));
        $migrationsDir = $root . '/database/migrations';

        $pdo = (new PdoConnectionFactory($dbPath))->create();
        $migrator = new SqliteMigrator($pdo, $migrationsDir);

        $all = $migrator->listAllMigrations();
        if (!in_array($version, $all, true)) {
            $output->writeln("<error>Unknown migration: {$version}</error>");
            $output->writeln("<comment>Hint:</comment> check the filename in {$migrationsDir}");
            return Command::INVALID;
        }

        $migrator->ensureMigrationsTable();
        $applied = $migrator->listAppliedMigrations();

        if (isset($applied[$version]) && !$force) {
            $output->writeln("<error>Already marked as applied:</error> {$version} ({$applied[$version]})");
            $output->writeln("<comment>Use --force to overwrite applied_at</comment>");
            return Command::FAILURE;
        }

        if (isset($applied[$version]) && $force) {
            $stmt = $pdo->prepare("UPDATE schema_migrations SET applied_at = :applied_at WHERE version = :version;");
            $stmt->execute([
                ':version' => $version,
                ':applied_at' => $appliedAt ?? date('Y-m-d H:i:s'),
            ]);

            $output->writeln("<info>Updated applied migration:</info> {$version}");
            return Command::SUCCESS;
        }

        if ($appliedAt !== null) {
            $stmt = $pdo->prepare("INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at);");
            $stmt->execute([':version' => $version, ':applied_at' => $appliedAt]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO schema_migrations (version, applied_at) VALUES (:version, datetime('now'));");
            $stmt->execute([':version' => $version]);
        }

        $output->writeln("<info>Marked as applied:</info> {$version}");
        return Command::SUCCESS;
    }
}
