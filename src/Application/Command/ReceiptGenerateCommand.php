<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\UseCase\GenerateReceiptsForMonth;
use RentReceiptCli\Application\Cli\ConsoleInputValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RentReceiptCli\Application\Port\Logger;





final class ReceiptGenerateCommand extends Command
{
    public function __construct(
        private readonly Logger $logger,
        private readonly GenerateReceiptsForMonth $useCase,
    ) {
        parent::__construct();
    }

    protected static $defaultName = 'receipt:generate';
    protected static $defaultDescription = 'Generate rent receipts PDFs for a given month';

    protected function configure(): void
    {
        $this
            ->addArgument('month', InputArgument::REQUIRED, 'Month to generate (format: YYYY-MM)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not generate anything, only show what would happen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $month = (string) $input->getArgument('month');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!ConsoleInputValidator::isValidMonth($month)) {
            $output->writeln('<error>Invalid month format. Expected YYYY-MM (e.g. 2026-01)</error>');
            return Command::INVALID;
        }
        $result = $this->useCase->execute($month, $dryRun);

        if ($dryRun) {
            $output->writeln("Dry-run: computed receipts for <info>{$month}</info> (no DB write)");
        } else {
            $output->writeln("Generated receipts for <info>{$month}</info>");
        }

        $output->writeln(sprintf('Created: <info>%d</info>', count($result->created)));
        $output->writeln(sprintf('Skipped: <comment>%d</comment>', count($result->skipped)));

        // Optional: show details (useful for debugging)
        foreach ($result->created as $row) {
            $output->writeln(sprintf(
                ' + created receipt for tenant #%d (payment #%d) -> %s',
                (int) $row['tenant_id'],
                (int) $row['rent_payment_id'],
                (string) $row['pdf_path']
            ));
        }

        foreach ($result->skipped as $row) {
            $output->writeln(sprintf(
                ' - skipped tenant #%d (%s)',
                (int) $row['tenant_id'],
                (string) $row['reason']
            ));
        }

        return Command::SUCCESS;
    }
}
