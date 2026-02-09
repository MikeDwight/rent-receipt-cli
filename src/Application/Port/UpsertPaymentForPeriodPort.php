<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Port;

/**
 * Application port: upsert a single rent payment for (tenant, property, period).
 * Amounts are typically taken from the property; paid_at is set by the caller.
 *
 * @return array{payment_id: ?int, action: 'created'|'updated'|'skipped_in_dry_run'}
 */
interface UpsertPaymentForPeriodPort
{
    public function upsert(
        int $tenantId,
        int $propertyId,
        string $period,
        \DateTimeImmutable $paidAt,
        bool $dryRun
    ): array;
}
