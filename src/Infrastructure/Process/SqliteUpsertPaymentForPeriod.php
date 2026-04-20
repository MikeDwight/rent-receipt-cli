<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Process;

use RentReceiptCli\Application\Port\PropertyRepository;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Application\Port\UpsertPaymentForPeriodPort;
use RentReceiptCli\Core\Domain\ValueObject\Month;

/**
 * Infrastructure implementation: upsert payment for (tenant, property, period).
 * Uses property defaults for rent_amount/charges_amount.
 */
final class SqliteUpsertPaymentForPeriod implements UpsertPaymentForPeriodPort
{
    public function __construct(
        private readonly RentPaymentRepository $payments,
        private readonly PropertyRepository $properties,
    ) {}

    public function upsert(
        int $tenantId,
        int $propertyId,
        string $period,
        \DateTimeImmutable $paidAt,
        bool $dryRun
    ): array {
        if ($dryRun) {
            return ['payment_id' => null, 'action' => 'skipped_in_dry_run'];
        }

        $property = $this->properties->findById($propertyId);
        if ($property === null) {
            throw new \RuntimeException("Property not found: #{$propertyId}");
        }

        $month = Month::fromString($period);
        $existing = $this->payments->findByTenantPropertyAndPeriod($tenantId, $propertyId, $period);

        if ($existing !== null) {
            // Preserve existing amounts — only update paid_at
            $this->payments->update(
                $existing['id'],
                $tenantId,
                $propertyId,
                $month,
                $existing['rent_amount'],
                $existing['charges_amount'],
                $existing['services_amount'],
                $paidAt,
                $existing['period_start'] ?? null,
                $existing['period_end'] ?? null,
            );

            return ['payment_id' => $existing['id'], 'action' => 'updated'];
        }

        // Create new payment with property defaults (services_amount defaults to 1500)
        $paymentId = $this->payments->create(
            $tenantId,
            $propertyId,
            $month,
            $property['rent_amount'],
            $property['charges_amount'],
            1500,
            $paidAt,
        );

        return ['payment_id' => $paymentId, 'action' => 'created'];
    }
}
