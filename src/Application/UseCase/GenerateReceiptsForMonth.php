<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\UseCase;

use RentReceiptCli\Application\DTO\GenerateReceiptsResult;
use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use RentReceiptCli\Core\Service\PdfGenerator;
use RentReceiptCli\Core\Service\ReceiptHtmlBuilder;
use RentReceiptCli\Core\Service\Pdf\PdfOptions;





final class GenerateReceiptsForMonth
{
    public function __construct(
        private readonly RentPaymentRepository $payments,
        private readonly ReceiptRepository $receipts,
        private readonly ReceiptHtmlBuilder $htmlBuilder,
        private readonly PdfGenerator $pdf,
        private readonly PdfOptions $pdfOptions,
        private readonly string $landlordName,
        private readonly string $landlordAddress,
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

            // Build template variables (minimal V1 mapping)
            $rentCents = (int) ($row['rent_amount'] ?? 0);
            $chargesCents = (int) ($row['charges_amount'] ?? 0);
            $vars = [
                'receipt_number' => sprintf('QL-%s-%06d', $m->toString(), $rentPaymentId),
                'period_machine' => $m->toString(),
                'period_label' => $m->toString(), // we'll improve to "février 2026" later
                'issued_at' => date('d/m/Y'),
                'paid_at' => (string)($row['paid_at'] ?? ''),
                'landlord_name' => $this->landlordName,
                'landlord_address' => $this->landlordAddress,
                'tenant_name' => (string) ($row['tenant_name'] ?? ('Tenant #' . $tenantId)),
                'tenant_address' => (string) ($row['tenant_address'] ?? ''),
                'property_label' => (string)($row['property_label'] ?? ''),
                'property_address' => (string) ($row['property_address'] ?? ''),
                'rent_amount_eur' => $this->formatCentsToEur($rentCents),
                'charges_amount_eur' => $this->formatCentsToEur($chargesCents),
                'total_amount_eur' => $this->formatCentsToEur($rentCents + $chargesCents),
            ];

            $html = $this->htmlBuilder->build($vars);
            $this->pdf->generateFromHtml($html, $pdfPath, $this->pdfOptions);


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
    private function formatCentsToEur(int $cents): string
        {
            $eur = $cents / 100;
            // French-style formatting: 1 000,00 €
            return number_format($eur, 2, ',', ' ') . ' €';
        }

}
