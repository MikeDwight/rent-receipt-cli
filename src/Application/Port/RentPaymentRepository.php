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
}