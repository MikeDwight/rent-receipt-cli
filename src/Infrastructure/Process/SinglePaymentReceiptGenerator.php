<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Process;

use DateTimeImmutable;
use RentReceiptCli\Application\Port\GenerateReceiptForPaymentPort;
use RentReceiptCli\Application\Port\OwnerRepository;
use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use RentReceiptCli\Core\Service\PdfGenerator;
use RentReceiptCli\Core\Service\Pdf\PdfOptions;
use RentReceiptCli\Core\Service\ReceiptHtmlBuilder;

/**
 * Infrastructure implementation: generate PDF receipt for a single payment (idempotent).
 */
final class SinglePaymentReceiptGenerator implements GenerateReceiptForPaymentPort
{
    public function __construct(
        private readonly RentPaymentRepository $payments,
        private readonly ReceiptRepository $receipts,
        private readonly OwnerRepository $owners,
        private readonly ReceiptHtmlBuilder $htmlBuilder,
        private readonly PdfGenerator $pdf,
        private readonly PdfOptions $pdfOptions,
        private readonly string $landlordName,
        private readonly string $landlordAddress,
        private readonly string $landlordIssueCity,
    ) {}

    public function generate(int $paymentId, string $period, bool $dryRun, array $options = []): array
    {
        if ($dryRun) {
            return [
                'action' => 'skipped_in_dry_run',
                'receipt_id' => null,
                'pdf_path' => null,
            ];
        }

        // Check if receipt already exists (idempotence)
        $existing = $this->receipts->findByRentPaymentId($paymentId);
        if ($existing !== null) {
            return [
                'action' => 'skipped',
                'receipt_id' => $existing['id'],
                'pdf_path' => $existing['pdf_path'],
            ];
        }

        // Load payment with all details
        $paymentData = $this->payments->findOneWithDetails($paymentId);
        if ($paymentData === null) {
            throw new \RuntimeException("Payment not found: #{$paymentId}");
        }

        $month = Month::fromString($period);
        $tenantId = (int) $paymentData['tenant_id'];

        // Build PDF path (same format as GenerateReceiptsForMonth)
        $tenantSlug = $this->slugify((string) $paymentData['tenant_name']);
        $pdfPath = sprintf(dirname(__DIR__, 3) . '/var/receipts/quittance-%s-%s.pdf', $month->toString(), $tenantSlug);

        // Calculate period bounds (can be overridden by caller for partial months)
        $startDate = new DateTimeImmutable(sprintf('%04d-%02d-01', $month->year(), $month->month()));
        $endDate = $startDate->modify('last day of this month');

        $startOverride = $options['period_start'] ?? null;
        $endOverride   = $options['period_end'] ?? null;

        $periodStartDisplay = $startOverride
            ? (new DateTimeImmutable($startOverride))->format('d/m/Y')
            : $startDate->format('d/m/Y');
        $periodEndDisplay = $endOverride
            ? (new DateTimeImmutable($endOverride))->format('d/m/Y')
            : $endDate->format('d/m/Y');

        $isProrata = $startOverride !== null || $endOverride !== null;
        $periodLabel = $isProrata
            ? $periodStartDisplay . ' au ' . $periodEndDisplay
            : $this->formatMonthFr($month);

        $rentCents = (int) $paymentData['rent_amount'];
        $chargesCents = (int) $paymentData['charges_amount'];

        // Build HTML variables (same structure as GenerateReceiptsForMonth)
        $vars = [
            'receipt_number' => sprintf('QL-%s-%06d', $month->toString(), $paymentId),
            'period_machine' => $month->toString(),
            'period_label' => $periodLabel,
            'period_start' => $periodStartDisplay,
            'period_end' => $periodEndDisplay,
            'issued_at' => date('d/m/Y'),
            'issued_city' => $this->landlordIssueCity,
            'paid_at' => $this->formatDateFr((string) $paymentData['paid_at']),
            'landlord_name' => $this->landlordName,
            'landlord_address' => $this->landlordAddress,
            'tenant_name' => (string) $paymentData['tenant_name'],
            'tenant_address' => (string) $paymentData['tenant_address'],
            'property_label' => (string) $paymentData['property_label'],
            'property_address' => (string) $paymentData['property_address'],
            'rent_amount_eur' => $this->formatCentsToEur($rentCents),
            'charges_amount_eur' => $this->formatCentsToEur($chargesCents),
            'total_amount_eur' => $this->formatCentsToEur($rentCents + $chargesCents),
        ];

        // Generate PDF
        $html = $this->htmlBuilder->build($vars);
        $this->pdf->generateFromHtml($html, $pdfPath, $this->pdfOptions);

        // Create receipt record
        $receiptId = $this->receipts->create([
            'rent_payment_id' => $paymentId,
            'pdf_path' => $pdfPath,
        ]);

        return [
            'action' => 'generated',
            'receipt_id' => $receiptId,
            'pdf_path' => $pdfPath,
        ];
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
        ]);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
        return trim($text, '-');
    }

    private function formatCentsToEur(int $cents): string
    {
        $eur = $cents / 100;
        // French-style formatting: 1 000,00 €
        return number_format($eur, 2, ',', ' ') . ' €';
    }

    private function formatMonthFr(Month $month): string
    {
        $months = ['Janvier','Février','Mars','Avril','Mai','Juin',
                   'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        return $months[$month->month() - 1] . ' ' . $month->year();
    }

    private function formatDateFr(string $date): string
    {
        $months = ['janvier','février','mars','avril','mai','juin',
                   'juillet','août','septembre','octobre','novembre','décembre'];
        try {
            $d = new \DateTimeImmutable($date);
            return ltrim($d->format('d'), '0') . ' ' . $months[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
        } catch (\Throwable) {
            return $date;
        }
    }
}
