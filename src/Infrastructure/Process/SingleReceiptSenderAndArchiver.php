<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Process;

use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Application\Port\SendAndArchiveReceiptPort;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use RentReceiptCli\Core\Service\Dto\ArchiveReceiptRequest;
use RentReceiptCli\Core\Service\Dto\SendReceiptRequest;
use RentReceiptCli\Core\Service\ReceiptArchiverInterface;
use RentReceiptCli\Core\Service\ReceiptSenderInterface;

/**
 * Infrastructure implementation: send receipt by email and archive it (idempotent unless resend/rearchive).
 */
final class SingleReceiptSenderAndArchiver implements SendAndArchiveReceiptPort
{
    public function __construct(
        private readonly ReceiptRepository $receipts,
        private readonly ReceiptSenderInterface $sender,
        private readonly ReceiptArchiverInterface $archiver,
        private readonly string $nextcloudTargetDir = '',
    ) {}

    public function sendAndArchive(
        int $receiptId,
        string $period,
        int $tenantId,
        bool $dryRun,
        bool $resend,
        bool $rearchive
    ): array {
        if ($dryRun) {
            return [
                'email_action' => 'skipped_in_dry_run',
                'email_reason' => null,
                'archive_action' => 'skipped_in_dry_run',
                'archive_path' => null,
                'archive_reason' => null,
            ];
        }

        // Load receipt with all details
        $receipt = $this->receipts->findOneDetailed($receiptId);
        if ($receipt === null) {
            throw new \RuntimeException("Receipt not found: #{$receiptId}");
        }

        $pdfPath = (string) $receipt['pdf_path'];
        $tenantEmail = (string) $receipt['tenant_email'];
        $tenantName = (string) $receipt['tenant_name'];
        $sentAt = $receipt['sent_at'] ?? null;
        $archivedAt = $receipt['archived_at'] ?? null;

        $emailAction = 'skipped';
        $emailReason = null;
        $archiveAction = 'skipped';
        $archivePath = null;
        $archiveReason = null;

        // -----------------------------
        // Email sending
        // -----------------------------
        if ($sentAt !== null && !$resend) {
            $emailAction = 'skipped';
            $emailReason = 'already_sent';
        } else {
            // Validate email
            if (!filter_var($tenantEmail, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = 'invalid tenant email';
                $this->receipts->markSent($receiptId, $errorMessage);
                $emailAction = 'skipped';
                $emailReason = $errorMessage;
            } elseif ($pdfPath === '' || !is_file($pdfPath)) {
                $errorMessage = 'pdf not found: ' . $pdfPath;
                $this->receipts->markSent($receiptId, $errorMessage);
                $emailAction = 'skipped';
                $emailReason = $errorMessage;
            } else {
                // Send email
                $month = Month::fromString($period);
                $sendRes = $this->sender->send(new SendReceiptRequest(
                    toEmail: $tenantEmail,
                    toName: $tenantName,
                    subject: "Quittance de loyer {$month->toString()}",
                    bodyText: "Bonjour,\n\nVeuillez trouver en pièce jointe votre quittance de loyer.\n\nCordialement,",
                    pdfPath: $pdfPath,
                ));

                if (!$sendRes->success) {
                    $errorMessage = $sendRes->errorMessage ?? 'send failed';
                    $this->receipts->markSent($receiptId, $errorMessage);
                    $emailAction = 'skipped';
                    $emailReason = $errorMessage;
                } else {
                    $this->receipts->markSent($receiptId, null);
                    $emailAction = 'sent';
                }
            }
        }

        // -----------------------------
        // Archive
        // -----------------------------
        if ($archivedAt !== null && !$rearchive) {
            $archiveAction = 'skipped';
            $archiveReason = 'already_archived';
        } else {
            if ($pdfPath === '' || !is_file($pdfPath)) {
                $errorMessage = 'pdf not found: ' . $pdfPath;
                $this->receipts->markArchived($receiptId, null, $errorMessage);
                $archiveAction = 'skipped';
                $archiveReason = $errorMessage;
            } else {
                $month = Month::fromString($period);
                $filename = sprintf('receipt-%s-tenant-%d.pdf', $month->toString(), $tenantId);

                $prefix = trim($this->nextcloudTargetDir, '/');
                $remotePath = $prefix !== ''
                    ? $prefix . '/' . $filename
                    : sprintf('archives/%s/%s', $month->toString(), $filename);

                $archiveRes = $this->archiver->archive(new ArchiveReceiptRequest(
                    localPdfPath: $pdfPath,
                    remotePath: $remotePath,
                ));

                if (!$archiveRes->success) {
                    $errorMessage = $archiveRes->errorMessage ?? 'archive failed';
                    $this->receipts->markArchived($receiptId, null, $errorMessage);
                    $archiveAction = 'skipped';
                    $archiveReason = $errorMessage;
                } else {
                    $this->receipts->markArchived($receiptId, $archiveRes->archivedPath, null);
                    $archiveAction = 'uploaded';
                    $archivePath = $archiveRes->archivedPath;
                }
            }
        }

        return [
            'email_action' => $emailAction,
            'email_reason' => $emailReason,
            'archive_action' => $archiveAction,
            'archive_path' => $archivePath,
            'archive_reason' => $archiveReason,
        ];
    }
}
