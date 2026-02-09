<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Port;

use RentReceiptCli\Core\Domain\ValueObject\Month;

interface RentPaymentRepository
{
    /**
     * Returns all rent payments for the month with all data needed to generate receipts.
     *
     * @return array<int, array<string,mixed>>
     */
    public function findForMonth(Month $month): array;

    /**
     * Lists rent payments with optional filters.
     *
     * @return list<array{
     *   id:int,
     *   tenant_id:int,
     *   property_id:int,
     *   period:string,
     *   rent_amount:int,
     *   charges_amount:int,
     *   paid_at:string,
     *   created_at:string
     * }>
     */
    public function list(
        ?Month $month = null,
        ?int $tenantId = null,
        ?int $propertyId = null
    ): array;

    /**
     * Returns a single rent payment by id.
     *
     * @return array{
     *   id:int,
     *   tenant_id:int,
     *   property_id:int,
     *   period:string,
     *   rent_amount:int,
     *   charges_amount:int,
     *   paid_at:string,
     *   created_at:string
     * }|null
     */
    public function findById(int $id): ?array;

    /**
     * Finds a rent payment by tenant, property and period (for upsert logic).
     *
     * @return array{
     *   id:int,
     *   tenant_id:int,
     *   property_id:int,
     *   period:string,
     *   rent_amount:int,
     *   charges_amount:int,
     *   paid_at:string,
     *   created_at:string
     * }|null
     */
    public function findByTenantPropertyAndPeriod(int $tenantId, int $propertyId, string $period): ?array;

    /**
     * Finds a rent payment by id with all data needed to generate receipt (joins tenant, property).
     *
     * @return array{
     *   rent_payment_id:int,
     *   tenant_id:int,
     *   property_id:int,
     *   month:string,
     *   rent_amount:int,
     *   charges_amount:int,
     *   paid_at:string,
     *   tenant_name:string,
     *   tenant_email:string,
     *   tenant_address:string,
     *   property_label:string,
     *   property_address:string
     * }|null
     */
    public function findOneWithDetails(int $paymentId): ?array;

    /**
     * Creates a rent payment and returns its id.
     */
    public function create(
        int $tenantId,
        int $propertyId,
        Month $period,
        int $rentAmount,
        int $chargesAmount,
        \DateTimeImmutable $paidAt
    ): int;

    /**
     * Updates an existing rent payment.
     */
    public function update(
        int $id,
        int $tenantId,
        int $propertyId,
        Month $period,
        int $rentAmount,
        int $chargesAmount,
        \DateTimeImmutable $paidAt
    ): void;

    /**
     * Deletes a rent payment by id.
     */
    public function delete(int $id): void;
}