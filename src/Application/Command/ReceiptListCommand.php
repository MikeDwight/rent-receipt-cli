<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RentReceiptCli\Application\Cli\ConsoleInputValidator;


final class ReceiptListCommand extends Command
{
    protected static $defaultName = 'receipt:list';
    protected static $defaultDescription = 'List receipts and their status';

    protected function configure(): void
    {
        $this->addOption(
            'month',
            null,
            InputOption::VALUE_REQUIRED,
            'Filter by month (format: YYYY-MM)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $month = $input->getOption('month');

        if ($month !== null && !ConsoleInputValidator::isValidMonth((string) $month)) {
            $output->writeln('<error>Invalid --month format. Expected YYYY-MM (e.g. 2026-01)</error>');
            return Command::INVALID;
        }


        if ($month) {
            $output->writeln("Listing receipts for <info>{$month}</info> (stub)");
        } else {
            $output->writeln('Listing receipts (stub)');
        }

        return Command::SUCCESS;
    }
}
