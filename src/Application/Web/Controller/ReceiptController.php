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
            'rent_amount_eur'   => '800,00 €',
            'charges_amount_eur'=> '50,00 €',
            'total_amount_eur'  => '850,00 €',
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
        return $this->servePdf($response, (int) $args['id'], 'attachment');
    }

    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->servePdf($response, (int) $args['id'], 'inline');
    }

    private function servePdf(Response $response, int $id, string $disposition): Response
    {
        $receipt = $this->receipts->findOneDetailed($id);
        if ($receipt === null || !file_exists($receipt['pdf_path'])) {
            $this->flash('error', 'Quittance introuvable.');
            return $this->redirect($response, '/receipts');
        }

        $filename = basename($receipt['pdf_path']);
        $response->getBody()->write((string) file_get_contents($receipt['pdf_path']));
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', $disposition . '; filename="' . $filename . '"');
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
            'period_start'  => $payment['period_start'] ?? "{$year}-{$mon}-01",
            'period_end'    => $payment['period_end']   ?? "{$year}-{$mon}-" . str_pad((string) $lastDay, 2, '0', STR_PAD_LEFT),
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
