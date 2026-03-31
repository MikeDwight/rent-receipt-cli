<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RentReceiptCli\Application\Port\OwnerRepository;
use RentReceiptCli\Application\Port\TenantRepository;
use RentReceiptCli\Application\Port\PropertyRepository;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use Slim\Views\Twig;

final class DashboardController extends AbstractController
{
    public function __construct(
        Twig $twig,
        private readonly OwnerRepository $owners,
        private readonly TenantRepository $tenants,
        private readonly PropertyRepository $properties,
        private readonly RentPaymentRepository $payments,
        private readonly ReceiptRepository $receipts,
    ) {
        parent::__construct($twig);
    }

    public function index(Request $request, Response $response): Response
    {
        $currentMonth = Month::current();

        $recentPayments = $this->payments->list();
        $recentPayments = array_slice(array_reverse($recentPayments), 0, 5);

        $currentReceipts = $this->receipts->findByMonth($currentMonth);

        return $this->render($response, 'dashboard.html.twig', [
            'ownerCount'     => count($this->owners->listAll()),
            'tenantCount'    => count($this->tenants->listAll()),
            'propertyCount'  => count($this->properties->listAll()),
            'recentPayments' => $recentPayments,
            'currentReceipts'=> $currentReceipts,
            'currentMonth'   => $currentMonth->toString(),
            'tenants'        => $this->tenants->listAll(),
            'properties'     => $this->properties->listAll(),
        ]);
    }
}
