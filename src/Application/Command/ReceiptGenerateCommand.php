<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RentReceiptCli\Application\Cli\ConsoleInputValidator;


final class ReceiptGenerateCommand extends Command
{
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


        if ($dryRun) {
            $output->writeln("Dry-run: would generate receipts for <info>{$month}</info> (stub)");
        } else {
            $output->writeln("Generating receipts for <info>{$month}</info> (stub)");
        }

        return Command::SUCCESS;
    }
}
