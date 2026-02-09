<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Port;

/**
 * Application port: generate PDF receipt for a single rent payment (idempotent).
 *
 * @return array{action: 'generated'|'skipped'|'skipped_in_dry_run', receipt_id: ?int, pdf_path: ?string}
 */
interface GenerateReceiptForPaymentPort
{
    public function generate(
        int $paymentId,
        string $period,
        bool $dryRun
    ): array;
}
