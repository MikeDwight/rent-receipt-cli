<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\UseCase;

use RentReceiptCli\Application\DTO\GenerateReceiptsResult;
use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;

final class GenerateReceiptsForMonth
{
    public function __construct(
        private readonly RentPaymentRepository $payments,
        private readonly ReceiptRepository $receipts,
    ) {}

    public function execute(string $month): GenerateReceiptsResult
    {
        $m = Month::fromString($month);

        $result = new GenerateReceiptsResult();

        $rows = $this->payments->findForMonth($m);

        foreach ($rows as $row) {
            $tenantId = (int) $row['tenant_id'];

            // Required by our receipts schema (receipt references rent_payment)
            $rentPaymentId = (int) ($row['rent_payment_id'] ?? 0);
            if ($rentPaymentId <= 0) {
                // If this happens, it means SqliteRentPaymentRepository didn't SELECT rp.id AS rent_payment_id
                $result->skipped[] = [
                    'tenant_id' => $tenantId,
                    'month' => $m->toString(),
                    'reason' => 'missing_rent_payment_id',
                ];
                continue;
            }

            if ($this->receipts->existsForTenantAndMonth($tenantId, $m)) {
                $result->skipped[] = [
                    'tenant_id' => $tenantId,
                    'month' => $m->toString(),
                    'reason' => 'receipt_already_exists',
                ];
                continue;
            }

            // V1: deterministic PDF path (actual PDF generation comes later in Thread 8)
            $pdfPath = sprintf('var/receipts/receipt-%s-tenant-%d.pdf', $m->toString(), $tenantId);

            $receiptId = $this->receipts->create([
                'rent_payment_id' => $rentPaymentId,
                'pdf_path' => $pdfPath,
            ]);

            $result->created[] = [
                'receipt_id' => $receiptId,
                'tenant_id' => $tenantId,
                'month' => $m->toString(),
                'rent_payment_id' => $rentPaymentId,
                'pdf_path' => $pdfPath,
            ];
        }

        return $result;
    }
}
