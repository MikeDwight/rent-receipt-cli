<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Application\Port\SendAndArchiveReceiptPort;
use RentReceiptCli\Application\UseCase\ProcessReceiptForPayment;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use RentReceiptCli\Core\Service\ReceiptHtmlBuilder;
use Slim\Views\Twig;

final class ReceiptController extends AbstractController
{
    public function __construct(
        Twig $twig,
        private readonly ReceiptRepository $receipts,
        private readonly RentPaymentRepository $payments,
        private readonly ProcessReceiptForPayment $processUseCase,
        private readonly SendAndArchiveReceiptPort $sendAndArchive,
        private readonly ReceiptHtmlBuilder $htmlBuilder,
        private readonly string $landlordCity = '',
    ) {
        parent::__construct($twig);
    }

    public function preview(Request $request, Response $response): Response
    {
        $today = date('d/m/Y');
        $html = $this->htmlBuilder->build([
            'receipt_number'    => 'QL-2026-04-000001',
            'period_machine'    => '2026-04',
            'period_label'      => '1er avril 2026 au 30 avril 2026',
            'period_start'      => '1er avril 2026',
            'period_end'        => '30 avril 2026',
            'issued_at'         => $today,
            'issued_city'       => 'Paris',
            'landlord_name'     => 'Jean Dupont',
            'landlord_address'  => '12 rue de la Paix, 75001 Paris',
            'tenant_name'       => 'Marie Martin',
            'tenant_address'    => '8 avenue des Fleurs, 75002 Paris',
            'property_label'    => 'Appartement T2 — Rue des Lilas',
            'property_address'  => '8 avenue des Fleurs, 75002 Paris',
            'rent_amount_eur'    => '800,00 €',
            'charges_amount_eur' => '50,00 €',
            'services_amount_eur'=> '15,00 €',
            'total_amount_eur'   => '865,00 €',
            'paid_at'           => '01/04/2026',
        ]);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $monthStr = $params['month'] ?? Month::current()->toString();

        try {
            $month = Month::fromString($monthStr);
        } catch (\Throwable) {
            $month = Month::current();
            $monthStr = $month->toString();
        }

        $receipts = $this->receipts->findByMonth($month);

        return $this->render($response, 'receipts/index.html.twig', [
            'receipts' => $receipts,
            'month'    => $monthStr,
        ]);
    }

    public function sendReceipt(Request $request, Response $response, array $args): Response
    {
        $receipt = $this->receipts->findOneDetailed((int) $args['id']);
        if ($receipt === null) {
            $this->flash('error', 'Quittance introuvable.');
            return $this->redirect($response, '/receipts');
        }

        try {
            $result = $this->sendAndArchive->sendAndArchive(
                $receipt['id'],
                $receipt['period'],
                $receipt['tenant_id'],
                false,
                false,
                false,
            );
            $parts = [];
            $parts[] = 'Email : ' . ($result['email_action'] ?? 'skipped');
            $parts[] = 'Archive : ' . ($result['archive_action'] ?? 'skipped');
            $this->flash('success', implode(' | ', $parts));
        } catch (\Throwable $e) {
            $this->flash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirect($response, '/receipts?month=' . urlencode($receipt['period']));
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $receipt = $this->receipts->findOneDetailed((int) $args['id']);
        if ($receipt === null) {
            $this->flash('error', 'Quittance introuvable.');
            return $this->redirect($response, '/receipts');
        }
        $month = $receipt['period'];
        $this->receipts->delete($receipt['id']);
        $this->flash('success', 'Quittance supprimée.');
        return $this->redirect($response, '/receipts?month=' . urlencode($month));
    }

    public function download(Request $request, Response $response, array $args): Response
    {
        $receipt = $this->receipts->findOneDetailed((int) $args['id']);
        if ($receipt === null || !file_exists($receipt['pdf_path'])) {
            $this->flash('error', 'Quittance introuvable.');
            return $this->redirect($response, '/receipts');
        }
        $filename = basename($receipt['pdf_path']);
        $response->getBody()->write((string) file_get_contents($receipt['pdf_path']));
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function view(Request $request, Response $response, array $args): Response
    {
        $receipt = $this->receipts->findOneDetailed((int) $args['id']);
        if ($receipt === null || !file_exists($receipt['pdf_path'])) {
            $response->getBody()->write('<p>Quittance introuvable.</p>');
            return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
        }

        $filename = basename($receipt['pdf_path']);
        $response->getBody()->write((string) file_get_contents($receipt['pdf_path']));
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    public function viewHtml(Request $request, Response $response, array $args): Response
    {
        $receipt = $this->receipts->findOneForPreview((int) $args['id']);
        if ($receipt === null) {
            $response->getBody()->write('<p>Quittance introuvable.</p>');
            return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
        }

        $rentCents     = (int) $receipt['rent_amount'];
        $chargesCents  = (int) $receipt['charges_amount'];
        $servicesCents = (int) ($receipt['services_amount'] ?? 0);
        $totalCents    = $rentCents + $chargesCents + $servicesCents;

        $periodStart = $this->formatDateFr((string) $receipt['period_start']);
        $periodEnd   = $this->formatDateFr((string) $receipt['period_end']);

        $html = $this->htmlBuilder->build([
            'receipt_number'     => sprintf('QL-%s-%06d', $receipt['period'], $receipt['rent_payment_id']),
            'period_machine'     => (string) $receipt['period'],
            'period_label'       => $periodStart . ' au ' . $periodEnd,
            'period_start'       => $periodStart,
            'period_end'         => $periodEnd,
            'issued_at'          => date('d/m/Y'),
            'issued_city'        => $this->landlordCity,
            'landlord_name'      => (string) $receipt['owner_name'],
            'landlord_address'   => (string) $receipt['owner_address'],
            'tenant_name'        => (string) $receipt['tenant_name'],
            'tenant_address'     => (string) $receipt['tenant_address'],
            'property_label'     => (string) $receipt['property_label'],
            'property_address'   => (string) $receipt['property_address'],
            'rent_amount_eur'    => $this->formatCentsToEur($rentCents),
            'charges_amount_eur' => $this->formatCentsToEur($chargesCents),
            'services_amount_eur'=> $this->formatCentsToEur($servicesCents),
            'total_amount_eur'   => $this->formatCentsToEur($totalCents),
            'paid_at'            => date('d/m/Y', (int) strtotime((string) $receipt['paid_at'])),
        ]);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    private function formatCentsToEur(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
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

    private function formatMonthFr(int $month, int $year): string
    {
        $months = ['Janvier','Février','Mars','Avril','Mai','Juin',
                   'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        return ($months[$month - 1] ?? '') . ' ' . $year;
    }

    public function processFromPayment(Request $request, Response $response, array $args): Response
    {
        $payment = $this->payments->findById((int) $args['id']);
        if ($payment === null) {
            $this->flash('error', 'Paiement introuvable.');
            return $this->redirect($response, '/payments');
        }

        $period = $payment['period'];
        [$year, $mon] = explode('-', $period);
        $lastDay = (int) (new \DateTimeImmutable("{$year}-{$mon}-01"))->modify('last day of this month')->format('d');

        $options = [
            'period'        => $period,
            'period_start'  => $payment['period_start'] ?: null,
            'period_end'    => $payment['period_end']   ?: null,
            'paid_at'       => new \DateTimeImmutable($payment['paid_at']),
            'generate_only' => true,
        ];

        try {
            $result = $this->processUseCase->execute($payment['tenant_id'], $payment['property_id'], $options);

            $parts = [];
            $parts[] = 'Paiement : ' . $result->payment['action'];
            $parts[] = 'Quittance : ' . $result->receipt['action'];
            $parts[] = 'Email : ' . $result->email['action'];
            $parts[] = 'Archive : ' . $result->archive['action'];

            if (!empty($result->errors)) {
                $this->flash('error', implode(' | ', $result->errors));
            } else {
                $this->flash('success', implode(' | ', $parts));
            }
        } catch (\Throwable $e) {
            $this->flash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirect($response, '/receipts?month=' . urlencode($period));
    }
}
