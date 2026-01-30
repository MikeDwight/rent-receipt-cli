<?php

declare(strict_types=1);

namespace RentReceiptCli\CLI\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use RentReceiptCli\Infrastructure\Seed\YamlSeedLoader;
use RuntimeException;


final class SeedImportCommand extends Command
{
    protected static $defaultName = 'seed:import';

    protected function configure(): void
    {
        $this
            ->setDescription('Import seed data into the database')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Validate and simulate the import without writing to the database'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
{
    $dryRun = (bool) $input->getOption('dry-run');

    if ($dryRun) {
        $output->writeln('<comment>[DRY-RUN] No database changes will be made</comment>');
    }

    $seedPath = 'seed/seed.yml';
    $loader = new YamlSeedLoader();

    try {
        $seed = $loader->load($seedPath);
    } catch (RuntimeException $e) {
        $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
        return Command::FAILURE;
    }

    $pdo = new \PDO('sqlite:database/database.sqlite');
    $importer = new \RentReceiptCli\Application\Seed\SeedImporter($pdo);

    $report = $importer->import($seed, $dryRun);

    foreach ($report->all() as $line) {
        $output->writeln('✔ ' . $line);
    }


    return Command::SUCCESS;
}

}
