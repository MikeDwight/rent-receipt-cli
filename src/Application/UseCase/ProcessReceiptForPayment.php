<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\UseCase;

use RentReceiptCli\Application\DTO\ProcessReceiptForPaymentResult;
use RentReceiptCli\Application\Port\GenerateReceiptForPaymentPort;
use RentReceiptCli\Application\Port\SendAndArchiveReceiptPort;
use RentReceiptCli\Application\Port\UpsertPaymentForPeriodPort;
use RentReceiptCli\Core\Domain\ValueObject\Month;

/**
 * One-click flow: upsert payment for period, generate receipt PDF, send email and archive.
 * No direct SQL; delegates to application ports.
 */
final class ProcessReceiptForPayment
{
    private const TIMEZONE = 'Europe/Paris';

    public function __construct(
        private readonly UpsertPaymentForPeriodPort $upsertPayment,
        private readonly GenerateReceiptForPaymentPort $generateReceipt,
        private readonly SendAndArchiveReceiptPort $sendAndArchive,
    ) {}

    /**
     * @param array{period: ?string, paid_at: ?\DateTimeImmutable, dry_run: bool, resend: bool, rearchive: bool} $options
     */
    public function execute(int $tenantId, int $propertyId, array $options = []): ProcessReceiptForPaymentResult
    {
        $tz = new \DateTimeZone(self::TIMEZONE);
        $period = $options['period'] ?? Month::current($tz)->toString();
        $paidAt = $options['paid_at'] ?? new \DateTimeImmutable('today', $tz);
        $dryRun = $options['dry_run'] ?? false;
        $resend = $options['resend'] ?? false;
        $rearchive = $options['rearchive'] ?? false;

        $input = [
            'tenant_id' => $tenantId,
            'property_id' => $propertyId,
            'period' => $period,
            'paid_at' => $paidAt->format('Y-m-d'),
            'flags' => [
                'dry_run' => $dryRun,
                'resend' => $resend,
                'rearchive' => $rearchive,
            ],
        ];

        if ($dryRun) {
            return new ProcessReceiptForPaymentResult(
                input: $input,
                payment: ['action' => 'skipped_in_dry_run', 'payment_id' => null],
                receipt: ['action' => 'skipped_in_dry_run', 'receipt_id' => null, 'pdf_path' => null],
                email: ['action' => 'skipped_in_dry_run', 'reason' => null],
                archive: ['action' => 'skipped_in_dry_run', 'archive_path' => null, 'reason' => null],
                errors: [],
            );
        }

        $errors = [];

        $paymentResult = $this->upsertPayment->upsert($tenantId, $propertyId, $period, $paidAt, false);
        $paymentId = $paymentResult['payment_id'] ?? null;
        $paymentAction = $paymentResult['action'] ?? 'skipped';

        if ($paymentId === null) {
            $errors[] = 'Upsert payment did not return a payment id';
        }

        $receiptAction = 'skipped';
        $receiptId = null;
        $pdfPath = null;

        if ($paymentId !== null) {
            $genResult = $this->generateReceipt->generate($paymentId, $period, false);
            $receiptAction = $genResult['action'] ?? 'skipped';
            $receiptId = $genResult['receipt_id'] ?? null;
            $pdfPath = $genResult['pdf_path'] ?? null;
        }

        $emailAction = 'skipped';
        $emailReason = null;
        $archiveAction = 'skipped';
        $archivePath = null;
        $archiveReason = null;

        if ($receiptId !== null) {
            $sendResult = $this->sendAndArchive->sendAndArchive(
                $receiptId,
                $period,
                $tenantId,
                false,
                $resend,
                $rearchive,
            );
            $emailAction = $sendResult['email_action'] ?? 'skipped';
            $emailReason = $sendResult['email_reason'] ?? null;
            $archiveAction = $sendResult['archive_action'] ?? 'skipped';
            $archivePath = $sendResult['archive_path'] ?? null;
            $archiveReason = $sendResult['archive_reason'] ?? null;
        }

        return new ProcessReceiptForPaymentResult(
            input: $input,
            payment: ['action' => $paymentAction, 'payment_id' => $paymentId],
            receipt: ['action' => $receiptAction, 'receipt_id' => $receiptId, 'pdf_path' => $pdfPath],
            email: ['action' => $emailAction, 'reason' => $emailReason],
            archive: ['action' => $archiveAction, 'archive_path' => $archivePath, 'reason' => $archiveReason],
            errors: $errors,
        );
    }
}
