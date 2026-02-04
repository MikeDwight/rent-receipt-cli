<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\UseCase;

use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use RentReceiptCli\Core\Service\ReceiptArchiverInterface;
use RentReceiptCli\Core\Service\ReceiptSenderInterface;
use RentReceiptCli\Core\Service\Dto\ArchiveReceiptRequest;
use RentReceiptCli\Core\Service\Dto\SendReceiptRequest;

final class SendReceiptsForMonth
{
    public function __construct(
        private readonly ReceiptRepository $receipts,
        private readonly ReceiptSenderInterface $sender,
        private readonly ReceiptArchiverInterface $archiver,
        private readonly string $nextcloudTargetDir = '',
    ) {}

    /**
     * @return array{sent:int, skipped:int}
     */
    public function execute(Month $month, bool $dryRun): array
    {
        $pending = $this->receipts->findPendingByMonth($month);

        $processed = 0;
        $failed = 0;
        $sent = 0;
        $skipped = 0;

        foreach ($pending as $r) {
            $processed++;
            if ($dryRun) {
                $skipped++;
                continue;
            }


            // TEMP: sender/archiver vont être branchés au bloc suivant
            $sendRes = $this->sender->send(new SendReceiptRequest(
                toEmail: (string)($r['tenant_email'] ?? ''),
                toName: (string)($r['tenant_name'] ?? ''),
                subject: "Quittance de loyer {$month->toString()}",
                bodyText: "Bonjour,\n\nVeuillez trouver en pièce jointe votre quittance de loyer.\n\nCordialement,",
                pdfPath: (string)$r['pdf_path'],
            ));

            if (!$sendRes->success) {
                $this->receipts->markSent((int)$r['id'], $sendRes->errorMessage ?? 'send failed');
                $failed++;
                continue;
            }

            $this->receipts->markSent((int)$r['id'], null);

            $filename = sprintf('receipt-%s-tenant-%d.pdf', $month->toString(), (int) $r['tenant_id']);

            $prefix = trim($this->nextcloudTargetDir, '/');

            $remotePath = $prefix !== ''
                ? $prefix . '/' . $filename
                : sprintf('archives/%s/%s', $month->toString(), $filename);

            $archiveRes = $this->archiver->archive(new ArchiveReceiptRequest(
                localPdfPath: (string) $r['pdf_path'],
                remotePath: $remotePath,
            ));


            if (!$archiveRes->success) {
                $this->receipts->markArchived((int)$r['id'], null, $archiveRes->errorMessage ?? 'archive failed');
                $sent++;
                continue;
            }

            $this->receipts->markArchived((int)$r['id'], $archiveRes->archivedPath, null);
            $sent++;

        }

        return ['processed' => $processed, 'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }
}
