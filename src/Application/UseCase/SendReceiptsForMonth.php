<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\UseCase;

use RentReceiptCli\Application\Port\Logger;
use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use RentReceiptCli\Core\Service\Dto\ArchiveReceiptRequest;
use RentReceiptCli\Core\Service\Dto\SendReceiptRequest;
use RentReceiptCli\Core\Service\ReceiptArchiverInterface;
use RentReceiptCli\Core\Service\ReceiptSenderInterface;

final class SendReceiptsForMonth
{
    public function __construct(
        private readonly ReceiptRepository $receipts,
        private readonly ReceiptSenderInterface $sender,
        private readonly ReceiptArchiverInterface $archiver,
        private readonly Logger $logger,
        private readonly string $nextcloudTargetDir = '',
    ) {}

    /**
     * @return array{processed:int, sent:int, failed:int, skipped:int}
     */
    public function execute(Month $month, bool $dryRun, bool $force = false): array

    {

                if ($force) {
            $this->logger->warning('receipts.send.force.requested', [
                'month' => $month->toString(),
                'note' => 'force mode not wired yet (missing ReceiptRepository::findByMonth)',
            ]);
        }


        $pending = $force
            ? $this->receipts->findByMonth($month)
            : $this->receipts->findPendingByMonth($month);


        $this->logger->info('receipts.send.uc.start', [
            'month' => $month->toString(),
            'dry_run' => $dryRun ? 1 : 0,
            'pending' => count($pending),
            'force' => $force ? 1 : 0,

        ]);

        $processed = 0;
        $failed = 0;
        $sent = 0;
        $skipped = 0;

        foreach ($pending as $r) {
            $processed++;

            $receiptId = (int) ($r['id'] ?? 0);
            $tenantId = (int) ($r['tenant_id'] ?? 0);
            $tenantEmail = (string) ($r['tenant_email'] ?? '');
            $tenantName = (string) ($r['tenant_name'] ?? '');
            $pdfPath = (string) ($r['pdf_path'] ?? '');

            if ($dryRun) {
                $this->logger->info('receipts.send.item.dry_run', [
                    'receipt_id' => $receiptId,
                    'tenant_id' => $tenantId,
                    'email' => $tenantEmail,
                    'pdf' => $pdfPath,
                ]);

                $skipped++;
                continue;
            }

            $this->logger->info('receipts.send.item', [
                'receipt_id' => $receiptId,
                'tenant_id' => $tenantId,
                'email' => $tenantEmail,
                'pdf' => $pdfPath,
            ]);

            if (!filter_var($tenantEmail, FILTER_VALIDATE_EMAIL)) {
                $message = 'invalid tenant email';
                $this->logger->error('receipts.send.item.invalid_email', [
                    'receipt_id' => $receiptId,
                    'tenant_id' => $tenantId,
                    'email' => $tenantEmail,
                    'reason' => $message,
                ]);

                $this->receipts->markSent($receiptId, $message);
                $failed++;
                continue;
            }

            if ($pdfPath === '' || !is_file($pdfPath)) {
                $message = 'pdf not found: ' . $pdfPath;
                $this->logger->error('receipts.send.item.missing_pdf', [
                    'receipt_id' => $receiptId,
                    'tenant_id' => $tenantId,
                    'pdf' => $pdfPath,
                    'reason' => $message,
                ]);

                $this->receipts->markSent($receiptId, $message);
                $failed++;
                continue;
            }

            $sendRes = $this->sender->send(new SendReceiptRequest(
                toEmail: $tenantEmail,
                toName: $tenantName,
                subject: "Quittance de loyer {$month->toString()}",
                bodyText: "Bonjour,\n\nVeuillez trouver en pièce jointe votre quittance de loyer.\n\nCordialement,",
                pdfPath: $pdfPath,
            ));

            if (!$sendRes->success) {
                $this->logger->error('receipts.send.item.failed', [
                    'receipt_id' => $receiptId,
                    'error' => $sendRes->errorMessage ?? 'send failed',
                ]);

                $this->receipts->markSent($receiptId, $sendRes->errorMessage ?? 'send failed');
                $failed++;
                continue;
            }

            $this->receipts->markSent($receiptId, null);

            // NOTE: le pattern d'archive est volontairement simple et local au use case.
            // Il pourra être extrait dans un petit helper dédié si les besoins évoluent.
            $filename = sprintf('receipt-%s-tenant-%d.pdf', $month->toString(), $tenantId);

            $prefix = trim($this->nextcloudTargetDir, '/');
            $remotePath = $prefix !== ''
                ? $prefix . '/' . $filename
                : sprintf('archives/%s/%s', $month->toString(), $filename);

            $this->logger->info('receipts.archive.item', [
                'receipt_id' => $receiptId,
                'remotePath' => $remotePath,
            ]);

            $archiveRes = $this->archiver->archive(new ArchiveReceiptRequest(
                localPdfPath: $pdfPath,
                remotePath: $remotePath,
            ));

            if (!$archiveRes->success) {
                $this->logger->error('receipts.archive.item.failed', [
                    'receipt_id' => $receiptId,
                    'remotePath' => $remotePath,
                    'error' => $archiveRes->errorMessage ?? 'archive failed',
                ]);

                $this->receipts->markArchived($receiptId, null, $archiveRes->errorMessage ?? 'archive failed');

                // on considère "sent" même si l’archive échoue (email déjà envoyé)
                $sent++;
                continue;
            }

            $this->receipts->markArchived($receiptId, $archiveRes->archivedPath, null);
            $sent++;
        }

        $this->logger->info('receipts.send.uc.done', [
            'month' => $month->toString(),
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
             'force' => $force ? 1 : 0,

        ]);

        return [
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }
}
