<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RentReceiptCli\Application\Port\PropertyRepository;
use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Application\Port\TenantRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use Slim\Views\Twig;

final class PaymentController extends AbstractController
{
    public function __construct(
        Twig $twig,
        private readonly RentPaymentRepository $payments,
        private readonly ReceiptRepository $receipts,
        private readonly TenantRepository $tenants,
        private readonly PropertyRepository $properties,
    ) {
        parent::__construct($twig);
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $monthStr = $params['period'] ?? null;

        $month = null;
        if ($monthStr !== null && $monthStr !== '') {
            try {
                $month = Month::fromString($monthStr);
            } catch (\Throwable) {
                $month = null;
            }
        }

        $payments = $this->payments->list($month);
        $tenants = array_column($this->tenants->listAll(), null, 'id');
        $properties = array_column($this->properties->listAll(), null, 'id');

        $receiptsByPaymentId = [];
        foreach ($payments as $p) {
            $receipt = $this->receipts->findByRentPaymentId($p['id']);
            if ($receipt !== null) {
                $receiptsByPaymentId[$p['id']] = $receipt;
            }
        }

        return $this->render($response, 'payments/index.html.twig', [
            'payments'            => $payments,
            'tenants'             => $tenants,
            'properties'          => $properties,
            'receiptsByPaymentId' => $receiptsByPaymentId,
            'filter'              => ['period' => $monthStr ?? ''],
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $preselectedProperty = null;
        if (!empty($params['property_id'])) {
            $preselectedProperty = $this->properties->findById((int) $params['property_id']);
        }

        return $this->render($response, 'payments/form.html.twig', [
            'payment'             => null,
            'tenants'             => $this->tenants->listAll(),
            'properties'          => $this->properties->listAll(),
            'preselectedProperty' => $preselectedProperty,
            'currentMonth'        => Month::current()->toString(),
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        try {
            $period = Month::fromString((string) ($body['period'] ?? ''));
            $this->payments->create(
                (int) ($body['tenant_id'] ?? 0),
                (int) ($body['property_id'] ?? 0),
                $period,
                (int) round((float) ($body['rent_amount'] ?? 0) * 100),
                (int) round((float) ($body['charges_amount'] ?? 0) * 100),
                new \DateTimeImmutable((string) ($body['paid_at'] ?? 'today')),
            );
            $this->flash('success', 'Paiement créé.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Erreur : ' . $e->getMessage());
        }
        return $this->redirect($response, '/payments');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $payment = $this->payments->findById((int) $args['id']);
        if ($payment === null) {
            return $response->withStatus(404);
        }
        return $this->render($response, 'payments/form.html.twig', [
            'payment'             => $payment,
            'tenants'             => $this->tenants->listAll(),
            'properties'          => $this->properties->listAll(),
            'preselectedProperty' => null,
            'currentMonth'        => Month::current()->toString(),
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        try {
            $period = Month::fromString((string) ($body['period'] ?? ''));
            $this->payments->update(
                (int) $args['id'],
                (int) ($body['tenant_id'] ?? 0),
                (int) ($body['property_id'] ?? 0),
                $period,
                (int) round((float) ($body['rent_amount'] ?? 0) * 100),
                (int) round((float) ($body['charges_amount'] ?? 0) * 100),
                new \DateTimeImmutable((string) ($body['paid_at'] ?? 'today')),
            );
            $this->flash('success', 'Paiement mis à jour.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Erreur : ' . $e->getMessage());
        }
        return $this->redirect($response, '/payments');
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($this->receipts->findByRentPaymentId($id) !== null) {
            $this->flash('error', 'Impossible de supprimer ce paiement : une quittance a déjà été générée.');
            return $this->redirect($response, '/payments');
        }
        $this->payments->delete($id);
        $this->flash('success', 'Paiement supprimé.');
        return $this->redirect($response, '/payments');
    }
}
