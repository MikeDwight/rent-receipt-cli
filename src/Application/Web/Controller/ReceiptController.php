<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Application\Port\TenantRepository;
use RentReceiptCli\Application\Port\PropertyRepository;
use RentReceiptCli\Application\UseCase\ProcessReceiptForPayment;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use Slim\Views\Twig;

final class ReceiptController extends AbstractController
{
    public function __construct(
        Twig $twig,
        private readonly ReceiptRepository $receipts,
        private readonly RentPaymentRepository $payments,
        private readonly TenantRepository $tenants,
        private readonly PropertyRepository $properties,
        private readonly ProcessReceiptForPayment $processUseCase,
    ) {
        parent::__construct($twig);
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
            'receipts'   => $receipts,
            'month'      => $monthStr,
            'tenants'    => $this->tenants->listAll(),
            'properties' => $this->properties->listAll(),
        ]);
    }

    public function process(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $tenantId    = (int) ($body['tenant_id'] ?? 0);
        $propertyId  = (int) ($body['property_id'] ?? 0);
        $periodStart = (string) ($body['period_start'] ?? '');
        $periodEnd   = (string) ($body['period_end'] ?? '');
        $paidAt      = (string) ($body['paid_at'] ?? '');

        // Derive YYYY-MM period from start date
        $period = $periodStart !== '' ? substr($periodStart, 0, 7) : '';

        $options = [];
        if ($period !== '') {
            $options['period'] = $period;
        }
        if ($periodStart !== '') {
            $options['period_start'] = $periodStart;
        }
        if ($periodEnd !== '') {
            $options['period_end'] = $periodEnd;
        }
        if ($paidAt !== '') {
            try {
                $options['paid_at'] = new \DateTimeImmutable($paidAt);
            } catch (\Throwable) {}
        }

        try {
            $result = $this->processUseCase->execute($tenantId, $propertyId, $options);

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

        $month = $period !== '' ? $period : Month::current()->toString();
        return $this->redirect($response, '/receipts?month=' . urlencode($month));
    }

    public function downloadForPayment(Request $request, Response $response, array $args): Response
    {
        $receipt = $this->receipts->findByRentPaymentId((int) $args['id']);
        if ($receipt === null || !file_exists($receipt['pdf_path'])) {
            $this->flash('error', 'Quittance introuvable.');
            return $this->redirect($response, '/payments');
        }

        $filename = basename($receipt['pdf_path']);
        $response->getBody()->write((string) file_get_contents($receipt['pdf_path']));
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
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
            'period'       => $period,
            'period_start' => "{$year}-{$mon}-01",
            'period_end'   => "{$year}-{$mon}-" . str_pad((string) $lastDay, 2, '0', STR_PAD_LEFT),
            'paid_at'      => new \DateTimeImmutable($payment['paid_at']),
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
