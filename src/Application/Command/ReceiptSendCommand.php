<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RentReceiptCli\Application\Cli\ConsoleInputValidator;


final class ReceiptSendCommand extends Command
{
    protected static $defaultName = 'receipt:send';
    protected static $defaultDescription = 'Send generated receipts by email and archive them';

    protected function configure(): void
    {
        $this
            ->addArgument('month', InputArgument::REQUIRED, 'Month to send (format: YYYY-MM)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not send anything, only show what would happen')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force sending even if already marked as sent (future use)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $month = (string) $input->getArgument('month');
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        if (!ConsoleInputValidator::isValidMonth($month)) {
            $output->writeln('<error>Invalid month format. Expected YYYY-MM (e.g. 2026-01)</error>');
            return Command::INVALID;
        }


        $mode = $dryRun ? 'Dry-run: would send' : 'Sending';
        $output->writeln("{$mode} receipts for <info>{$month}</info> (stub)");

        if ($force) {
            $output->writeln('<comment>--force enabled (future behavior): would allow re-sending already sent receipts.</comment>');
        }

        return Command::SUCCESS;
    }
}
