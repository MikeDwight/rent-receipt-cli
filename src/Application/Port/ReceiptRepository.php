<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Port;

use RentReceiptCli\Core\Domain\ValueObject\Month;

interface ReceiptRepository
{
    public function existsForTenantAndMonth(int $tenantId, Month $month): bool;

    /**
     * Persists receipt and returns receipt id.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int;

    public function findPendingByMonth(\RentReceiptCli\Core\Domain\ValueObject\Month $month): array;

        /**
     * Returns all receipts for a month (including already sent ones).
     */
    public function findByMonth(Month $month): array;

        /**
     * Receipts already sent but not archived yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findSentNotArchivedByMonth(Month $month): array;



    public function markSent(int $receiptId, ?string $errorMessage): void;

    public function markArchived(int $receiptId, ?string $archivedPath, ?string $errorMessage): void;

    /**
     * Finds a receipt by rent_payment_id (idempotence check for single payment).
     *
     * @return array{id:int, rent_payment_id:int, pdf_path:string, sent_at:?string, archived_at:?string, created_at:string}|null
     */
    public function findByRentPaymentId(int $rentPaymentId): ?array;

    /**
     * Finds a receipt by id with all details needed for send/archive (joins tenant, payment, property, owner).
     *
     * @return array{
     *   id:int,
     *   rent_payment_id:int,
     *   pdf_path:string,
     *   sent_at:?string,
     *   send_error:?string,
     *   archived_at:?string,
     *   archive_path:?string,
     *   archive_error:?string,
     *   tenant_id:int,
     *   tenant_email:string,
     *   tenant_name:string,
     *   period:string
     * }|null
     */
    public function findOneDetailed(int $receiptId): ?array;
}

