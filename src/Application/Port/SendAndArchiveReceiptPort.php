<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Port;

/**
 * Application port: send receipt by email and archive it (idempotent unless resend/rearchive).
 *
 * @return array{
 *   email_action: string,
 *   email_reason: ?string,
 *   archive_action: string,
 *   archive_path: ?string,
 *   archive_reason: ?string
 * }
 */
interface SendAndArchiveReceiptPort
{
    public function sendAndArchive(
        int $receiptId,
        string $period,
        int $tenantId,
        bool $dryRun,
        bool $resend,
        bool $rearchive
    ): array;
}
