<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RentReceiptCli\Application\Port\ReceiptRepository;
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
        $tenantId   = (int) ($body['tenant_id'] ?? 0);
        $propertyId = (int) ($body['property_id'] ?? 0);
        $period     = (string) ($body['period'] ?? '');
        $paidAt     = (string) ($body['paid_at'] ?? '');

        $options = [];
        if ($period !== '') {
            $options['period'] = $period;
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
}
