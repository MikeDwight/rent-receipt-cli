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

    public function markSent(int $receiptId, ?string $errorMessage): void;

    public function markArchived(int $receiptId, ?string $archivedPath, ?string $errorMessage): void;

}

