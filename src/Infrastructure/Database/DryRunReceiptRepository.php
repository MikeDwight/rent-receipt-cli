<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Database;

use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;

/**
 * Decorator that prevents writes while keeping "exists" logic.
 */
final class DryRunReceiptRepository implements ReceiptRepository
{
    public function __construct(private readonly ReceiptRepository $inner) {}

    public function existsForTenantAndMonth(int $tenantId, Month $month): bool
    {
        return $this->inner->existsForTenantAndMonth($tenantId, $month);
    }

    public function create(array $data): int
    {
        // Do not write, but pretend success with a fake id.
        return 0;
    }
}
