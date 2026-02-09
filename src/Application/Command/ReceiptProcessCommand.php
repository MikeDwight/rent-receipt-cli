<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Cli\ConsoleInputValidator;
use RentReceiptCli\Application\DTO\ProcessReceiptForPaymentResult;
use RentReceiptCli\Application\Port\Logger;
use RentReceiptCli\Application\UseCase\ProcessReceiptForPayment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class ReceiptProcessCommand extends Command
{
    protected static $defaultName = 'receipt:process';
    protected static $defaultDescription = 'One-click: upsert payment, generate receipt, send email and archive';

    public function __construct(
        private readonly Logger $logger,
        private readonly ProcessReceiptForPayment $useCase,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('tenant-id', null, InputOption::VALUE_REQUIRED, 'Tenant id')
            ->addOption('property-id', null, InputOption::VALUE_REQUIRED, 'Property id')
            ->addOption('period', null, InputOption::VALUE_REQUIRED, 'Period override (YYYY-MM), default = current month Europe/Paris')
            ->addOption('paid-at', null, InputOption::VALUE_REQUIRED, 'Payment date override (YYYY-MM-DD), default = today Europe/Paris')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation only: no DB write, no PDF, no email, no upload')
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Do not ask for confirmation')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Do not ask for confirmation (alias of --no-interaction)')
            ->addOption('resend', null, InputOption::VALUE_NONE, 'Force send email even if already sent (use sparingly)')
            ->addOption('rearchive', null, InputOption::VALUE_NONE, 'Force upload even if already archived');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantIdRaw = (string) $input->getOption('tenant-id');
        $propertyIdRaw = (string) $input->getOption('property-id');

        if ($tenantIdRaw === '' || $propertyIdRaw === '') {
            $output->writeln('<error>--tenant-id and --property-id are required.</error>');
            return Command::FAILURE;
        }

        if (!ctype_digit($tenantIdRaw) || !ctype_digit($propertyIdRaw)) {
            $output->writeln('<error>--tenant-id and --property-id must be integers.</error>');
            return Command::INVALID;
        }

        $tenantId = (int) $tenantIdRaw;
        $propertyId = (int) $propertyIdRaw;

        $periodOverride = $input->getOption('period');
        if ($periodOverride !== null && !ConsoleInputValidator::isValidMonth((string) $periodOverride)) {
            $output->writeln('<error>Invalid --period format. Expected YYYY-MM.</error>');
            return Command::INVALID;
        }

        $paidAtRaw = $input->getOption('paid-at');
        if ($paidAtRaw !== null) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $paidAtRaw)) {
                $output->writeln('<error>Invalid --paid-at format. Expected YYYY-MM-DD.</error>');
                return Command::INVALID;
            }
            try {
                new \DateTimeImmutable((string) $paidAtRaw);
            } catch (\Throwable $e) {
                $output->writeln('<error>Invalid --paid-at date: ' . $e->getMessage() . '</error>');
                return Command::INVALID;
            }
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $noInteraction = (bool) $input->getOption('no-interaction') || (bool) $input->getOption('yes');
        $resend = (bool) $input->getOption('resend');
        $rearchive = (bool) $input->getOption('rearchive');

        $options = [
            'period' => $periodOverride !== null ? (string) $periodOverride : null,
            'paid_at' => $paidAtRaw !== null ? new \DateTimeImmutable((string) $paidAtRaw) : null,
            'dry_run' => $dryRun,
            'resend' => $resend,
            'rearchive' => $rearchive,
        ];

        if (!$dryRun && !$noInteraction) {
            $periodDisplay = $options['period'] ?? '(current month)';
            $paidAtDisplay = $options['paid_at'] !== null ? $options['paid_at']->format('Y-m-d') : '(today)';
            $output->writeln(sprintf(
                'About to process: tenant_id=%d property_id=%d period=%s paid_at=%s resend=%s rearchive=%s',
                $tenantId,
                $propertyId,
                $periodDisplay,
                $paidAtDisplay,
                $resend ? 'yes' : 'no',
                $rearchive ? 'yes' : 'no',
            ));
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Continue? [y/N] ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Aborted.');
                return Command::SUCCESS;
            }
        }

        try {
            $result = $this->useCase->execute($tenantId, $propertyId, $options);
        } catch (\Throwable $e) {
            $this->logger->error('receipt.process.failed', [
                'tenant_id' => $tenantId,
                'property_id' => $propertyId,
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);
            $output->writeln('[RESULT] ok warnings=0 errors=1');
            $output->writeln('[ERROR] message=' . $this->escapeResultValue((string) $e->getMessage()));
            return Command::FAILURE;
        }

        $this->writeMachineOutput($output, $result);

        $errors = $result->errors;
        $warnings = $this->countWarnings($result);

        if (\count($errors) > 0) {
            $output->writeln('[RESULT] ok warnings=' . $warnings . ' errors=' . \count($errors));
            return Command::FAILURE;
        }

        if ($warnings > 0) {
            $output->writeln('[RESULT] ok warnings=' . $warnings . ' errors=0');
            return 2;
        }

        $output->writeln('[RESULT] ok warnings=0 errors=0');
        return Command::SUCCESS;
    }

    private function writeMachineOutput(OutputInterface $output, ProcessReceiptForPaymentResult $result): void
    {
        $in = $result->input;
        $dryRun = $in['flags']['dry_run'] ? 1 : 0;
        $resend = $in['flags']['resend'] ? 1 : 0;
        $rearchive = $in['flags']['rearchive'] ? 1 : 0;

        $output->writeln(sprintf(
            '[INPUT] tenant_id=%d property_id=%d period=%s paid_at=%s dry_run=%d resend=%d rearchive=%d',
            $in['tenant_id'],
            $in['property_id'],
            $in['period'],
            $in['paid_at'],
            $dryRun,
            $resend,
            $rearchive,
        ));

        $p = $result->payment;
        $paymentId = $p['payment_id'] !== null ? (string) $p['payment_id'] : '';
        $output->writeln('[PAYMENT] action=' . $p['action'] . ' id=' . $paymentId);

        $r = $result->receipt;
        $receiptId = $r['receipt_id'] !== null ? (string) $r['receipt_id'] : '';
        $pdfPath = $r['pdf_path'] ?? '';
        $output->writeln('[RECEIPT] action=' . $r['action'] . ' id=' . $receiptId . ' pdf=' . $this->escapeResultValue($pdfPath));

        $e = $result->email;
        $output->writeln('[EMAIL] action=' . $e['action'] . ' reason=' . $this->escapeResultValue((string) ($e['reason'] ?? '')));

        $a = $result->archive;
        $archivePath = $a['archive_path'] ?? '';
        $archiveReason = $a['reason'] ?? '';
        $output->writeln('[ARCHIVE] action=' . $a['action'] . ' path=' . $this->escapeResultValue((string) $archivePath) . ' reason=' . $this->escapeResultValue((string) $archiveReason));
    }

    private function escapeResultValue(string $v): string
    {
        return str_replace(["\n", "\r", " "], [' ', ' ', ' '], trim($v));
    }

    /**
     * Counts warnings (degraded situations), excluding normal idempotent skips.
     * Warnings reflect actual issues: send_error, archive_error, invalid email, missing PDF, etc.
     * Normal skips (already_sent, already_archived) are not counted as warnings.
     */
    private function countWarnings(ProcessReceiptForPaymentResult $result): int
    {
        $n = 0;
        $emailReason = (string) ($result->email['reason'] ?? '');
        if ($emailReason !== '' && $result->email['action'] === 'skipped') {
            // Exclude normal idempotent skip (already_sent)
            if ($emailReason !== 'already_sent') {
                $n++;
            }
        }
        $archiveReason = (string) ($result->archive['reason'] ?? '');
        if ($archiveReason !== '' && $result->archive['action'] === 'skipped') {
            // Exclude normal idempotent skip (already_archived)
            if ($archiveReason !== 'already_archived') {
                $n++;
            }
        }
        return $n;
    }
}
