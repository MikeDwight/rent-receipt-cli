<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\DTO;

/**
 * Result of the one-click "receipt:process" flow for a single payment.
 *
 * Shape:
 * - input: tenant_id, property_id, period, paid_at, flags (dry_run, resend, rearchive)
 * - payment: action (created|updated|skipped_in_dry_run), payment_id (nullable)
 * - receipt: action (generated|skipped|skipped_in_dry_run), receipt_id (nullable), pdf_path (nullable)
 * - email: action (sent|skipped|skipped_in_dry_run), reason (nullable)
 * - archive: action (uploaded|skipped|skipped_in_dry_run), archive_path (nullable), reason (nullable)
 * - errors: list<string>
 */
final class ProcessReceiptForPaymentResult
{
    public function __construct(
        public readonly array $input,
        public readonly array $payment,
        public readonly array $receipt,
        public readonly array $email,
        public readonly array $archive,
        public readonly array $errors = [],
    ) {}
}
