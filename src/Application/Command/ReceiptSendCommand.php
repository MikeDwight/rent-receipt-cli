<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RentReceiptCli\Application\Cli\ConsoleInputValidator;
use RentReceiptCli\Application\Port\Logger;
use RentReceiptCli\Application\UseCase\SendReceiptsForMonth;
use RentReceiptCli\Core\Domain\ValueObject\Month;




final class ReceiptSendCommand extends Command
{
    public function __construct(
        private readonly Logger $logger,
        private readonly SendReceiptsForMonth $useCase,
    ) {
        parent::__construct();
    }

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

        $res = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $this->logger->info('receipts.send.start', [
            'month' => $month,
            'dry_run' => $dryRun ? 1 : 0,
            'force' => $force ? 1 : 0,
        ]);

        try {
            $monthVo = Month::fromString($month);
            $res = $this->useCase->execute($monthVo, $dryRun, $force);

            $this->logger->info('receipts.send.done', [
                'month' => $month,
                'processed' => $res['processed'] ?? null,
                'sent' => $res['sent'] ?? null,
                'failed' => $res['failed'] ?? null,
                'skipped' => $res['skipped'] ?? null,
                'dry_run' => $dryRun ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('receipts.send.failed', [
                'month' => $month,
                'dry_run' => $dryRun ? 1 : 0,
                'force' => $force ? 1 : 0,
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            $output->writeln('<error>Failed to send receipts. See logs for details.</error>');

            return Command::FAILURE;
        }
        $output->writeln(sprintf('Processed pending: %d', $res['processed']));
        $output->writeln(sprintf('Sent: %d', $res['sent']));
        $output->writeln(sprintf('Failed: %d', $res['failed']));
        $output->writeln(sprintf('Dry-run skipped: %d', $res['skipped']));

        return Command::SUCCESS;
    }
}
