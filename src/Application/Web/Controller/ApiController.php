<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RentReceiptCli\Application\Port\OwnerRepository;
use RentReceiptCli\Application\Port\PropertyRepository;
use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Application\Port\TenantRepository;
use RentReceiptCli\Application\UseCase\ProcessReceiptForPayment;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use RentReceiptCli\Core\Exception\DomainException;

final class ApiController
{
    public function __construct(
        private readonly OwnerRepository $owners,
        private readonly TenantRepository $tenants,
        private readonly PropertyRepository $properties,
        private readonly RentPaymentRepository $payments,
        private readonly ReceiptRepository $receipts,
        private readonly ProcessReceiptForPayment $processUseCase,
        private readonly array $config,
    ) {}

    // ---- helpers ------------------------------------------------------------

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function error(Response $response, string $message, int $status = 400): Response
    {
        return $this->json($response, ['error' => $message], $status);
    }

    private function parsePeriod(?string $value): Month
    {
        if ($value !== null && preg_match('/^\d{4}-\d{2}$/', $value)) {
            try { return Month::fromString($value); } catch (DomainException) {}
        }
        return Month::current(new \DateTimeZone('Europe/Paris'));
    }

    /** Index payments by payment_id, embed the matching receipt. */
    private function embedReceipts(array $payments, Month $month): array
    {
        $receipts = $this->receipts->findByMonth($month);
        $byPaymentId = [];
        foreach ($receipts as $r) {
            $byPaymentId[(int) $r['rent_payment_id']] = $r;
        }
        return array_map(function (array $p) use ($byPaymentId): array {
            $p['rent_amount']     = (int) $p['rent_amount'];
            $p['charges_amount']  = (int) $p['charges_amount'];
            $p['services_amount'] = (int) ($p['services_amount'] ?? 0);
            $r = $byPaymentId[(int) $p['id']] ?? null;
            $p['receipt'] = $r ? [
                'id'            => (int) $r['id'],
                'pdf_path'      => $r['pdf_path'],
                'generated_at'  => $r['created_at'] ?? null,
                'sent_at'       => $r['sent_at'],
                'send_error'    => $r['send_error'],
                'archived_at'   => $r['archived_at'],
                'archive_path'  => $r['archive_path'],
                'archive_error' => $r['archive_error'],
            ] : null;
            return $p;
        }, $payments);
    }

    // ---- GET /api/owner -----------------------------------------------------

    public function getOwner(Request $request, Response $response): Response
    {
        $landlordCity = (string) ($this->config['landlord']['city'] ?? '');
        $owners = array_map(static function (array $o) use ($landlordCity): array {
            $o['city'] = $landlordCity;
            return $o;
        }, $this->owners->listAll());
        return $this->json($response, $owners);
    }

    // ---- GET /api/tenants ---------------------------------------------------

    public function getTenants(Request $request, Response $response): Response
    {
        $tenants = $this->tenants->listAll();

        // Derive property_id from most recent payment (any period)
        $allPayments = $this->payments->list();
        $lastPropertyByTenant = [];
        foreach ($allPayments as $p) {
            $lastPropertyByTenant[(int) $p['tenant_id']] = (int) $p['property_id'];
        }

        $tenants = array_map(static function (array $t) use ($lastPropertyByTenant): array {
            $t['property_id'] = $lastPropertyByTenant[$t['id']] ?? null;
            return $t;
        }, $tenants);

        return $this->json($response, $tenants);
    }

    // ---- POST /api/tenants --------------------------------------------------

    public function upsertTenant(Request $request, Response $response): Response
    {
        $body      = (array) ($request->getParsedBody() ?? []);
        $fullName  = trim((string) ($body['full_name'] ?? ''));
        $email     = trim((string) ($body['email'] ?? ''));
        $address   = trim((string) ($body['address'] ?? ''));
        $id        = isset($body['id']) && $body['id'] !== '' ? (int) $body['id'] : null;

        if ($fullName === '' || $email === '' || $address === '') {
            return $this->error($response, 'full_name, email et address sont requis');
        }

        if ($id !== null) {
            $this->tenants->update($id, $fullName, $email, $address);
            return $this->json($response, ['id' => $id, 'action' => 'updated']);
        }

        $newId = $this->tenants->create($fullName, $email, $address);
        return $this->json($response, ['id' => $newId, 'action' => 'created'], 201);
    }

    // ---- DELETE /api/tenants/{id} ------------------------------------------

    public function deleteTenant(Request $request, Response $response, array $args): Response
    {
        $this->tenants->delete((int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }

    // ---- GET /api/properties ------------------------------------------------

    public function getProperties(Request $request, Response $response): Response
    {
        $properties = $this->properties->listAll();

        // Derive current occupant from most recent payment (any period)
        $allPayments = $this->payments->list();
        $tenants     = $this->tenants->listAll();
        $tenantById  = [];
        foreach ($tenants as $t) { $tenantById[(int) $t['id']] = $t; }

        $lastTenantByProperty = [];
        foreach ($allPayments as $p) {
            $lastTenantByProperty[(int) $p['property_id']] = (int) $p['tenant_id'];
        }

        $properties = array_map(static function (array $p) use ($lastTenantByProperty, $tenantById): array {
            $p['rent_amount']    = (int) $p['rent_amount'];
            $p['charges_amount'] = (int) $p['charges_amount'];
            $tenantId = $lastTenantByProperty[$p['id']] ?? null;
            $p['occupant'] = $tenantId ? ($tenantById[$tenantId] ?? null) : null;
            return $p;
        }, $properties);

        return $this->json($response, $properties);
    }

    // ---- POST /api/properties -----------------------------------------------

    public function upsertProperty(Request $request, Response $response): Response
    {
        $body          = (array) ($request->getParsedBody() ?? []);
        $label         = trim((string) ($body['label'] ?? ''));
        $address       = trim((string) ($body['address'] ?? ''));
        $rentAmount    = (int) ($body['rent_amount'] ?? 0);
        $chargesAmount = (int) ($body['charges_amount'] ?? 0);
        $id            = isset($body['id']) && $body['id'] !== '' ? (int) $body['id'] : null;

        if ($label === '' || $address === '') {
            return $this->error($response, 'label et address sont requis');
        }

        $owners  = $this->owners->listAll();
        $ownerId = empty($owners) ? 1 : (int) $owners[0]['id'];

        if ($id !== null) {
            $this->properties->update($id, $ownerId, $label, $address, $rentAmount, $chargesAmount);
            return $this->json($response, ['id' => $id, 'action' => 'updated']);
        }

        $newId = $this->properties->create($ownerId, $label, $address, $rentAmount, $chargesAmount);
        return $this->json($response, ['id' => $newId, 'action' => 'created'], 201);
    }

    // ---- DELETE /api/properties/{id} ---------------------------------------

    public function deleteProperty(Request $request, Response $response, array $args): Response
    {
        $this->properties->delete((int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }

    // ---- GET /api/payments?period=YYYY-MM -----------------------------------

    public function getPayments(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $month  = $this->parsePeriod($params['period'] ?? null);
        $payments = $this->payments->list($month);
        return $this->json($response, $this->embedReceipts($payments, $month));
    }

    // ---- POST /api/payments -------------------------------------------------

    public function upsertPayment(Request $request, Response $response): Response
    {
        $body          = (array) ($request->getParsedBody() ?? []);
        $tenantId      = (int) ($body['tenant_id'] ?? 0);
        $propertyId    = (int) ($body['property_id'] ?? 0);
        $periodStr     = (string) ($body['period'] ?? '');
        $rentAmount    = (int) ($body['rent_amount'] ?? 0);
        $chargesAmount = (int) ($body['charges_amount'] ?? 0);
        $servicesAmount= (int) ($body['services_amount'] ?? 0);
        $paidAtStr     = (string) ($body['paid_at'] ?? date('Y-m-d'));

        if (!$tenantId || !$propertyId || $periodStr === '') {
            return $this->error($response, 'tenant_id, property_id, period sont requis');
        }

        try {
            $month  = Month::fromString($periodStr);
            $paidAt = new \DateTimeImmutable($paidAtStr);
        } catch (\Throwable $e) {
            return $this->error($response, $e->getMessage());
        }

        $existing = $this->payments->findByTenantPropertyAndPeriod($tenantId, $propertyId, $periodStr);
        if ($existing !== null) {
            $this->payments->update(
                $existing['id'], $tenantId, $propertyId, $month,
                $rentAmount, $chargesAmount, $servicesAmount, $paidAt
            );
            return $this->json($response, ['id' => $existing['id'], 'action' => 'updated']);
        }

        $id = $this->payments->create(
            $tenantId, $propertyId, $month,
            $rentAmount, $chargesAmount, $servicesAmount, $paidAt
        );
        return $this->json($response, ['id' => $id, 'action' => 'created'], 201);
    }

    // ---- GET /api/receipts?period=YYYY-MM -----------------------------------

    public function getReceipts(Request $request, Response $response): Response
    {
        $params   = $request->getQueryParams();
        $month    = $this->parsePeriod($params['period'] ?? null);
        $receipts = $this->receipts->findByMonth($month);

        // Enrich with property info via payment
        $payments   = $this->payments->list($month);
        $paymentById = [];
        foreach ($payments as $p) { $paymentById[(int) $p['id']] = $p; }
        $propList    = $this->properties->listAll();
        $propertyById = [];
        foreach ($propList as $p) { $propertyById[(int) $p['id']] = $p; }

        $result = array_map(static function (array $r) use ($paymentById, $propertyById): array {
            $r['id']             = (int) $r['id'];
            $r['rent_amount']    = (int) $r['rent_amount'];
            $r['charges_amount'] = (int) $r['charges_amount'];
            $payment  = $paymentById[(int) $r['rent_payment_id']] ?? null;
            $r['property'] = $payment ? ($propertyById[(int) $payment['property_id']] ?? null) : null;
            return $r;
        }, $receipts);

        return $this->json($response, $result);
    }

    // ---- GET /api/dashboard?period=YYYY-MM ----------------------------------

    public function getDashboard(Request $request, Response $response): Response
    {
        $params     = $request->getQueryParams();
        $month      = $this->parsePeriod($params['period'] ?? null);
        $properties = $this->properties->listAll();
        $payments   = $this->payments->list($month);
        $receipts   = $this->receipts->findByMonth($month);

        $receiptByPaymentId = [];
        foreach ($receipts as $r) {
            $receiptByPaymentId[(int) $r['rent_payment_id']] = $r;
        }

        $expected = 0;
        foreach ($properties as $p) {
            $expected += (int) $p['rent_amount'] + (int) $p['charges_amount'];
        }

        $collected = 0;
        foreach ($payments as $p) {
            $collected += (int) $p['rent_amount'] + (int) $p['charges_amount'];
        }

        $sent = $archived = $todoCount = 0;
        foreach ($receipts as $r) {
            if ($r['sent_at']) $sent++;
            if ($r['archived_at']) $archived++;
        }
        foreach ($payments as $p) {
            $r = $receiptByPaymentId[(int) $p['id']] ?? null;
            if (!$r) {
                $todoCount++;
            } elseif (!empty($r['archive_error']) || ($r['sent_at'] && !$r['archived_at'])) {
                $todoCount++;
            } elseif (!empty($r['send_error'])) {
                $todoCount++;
            }
        }

        return $this->json($response, [
            'period'     => $month->toString(),
            'lots'       => count($properties),
            'expected'   => $expected,
            'collected'  => $collected,
            'sent'       => $sent,
            'archived'   => $archived,
            'todo_count' => $todoCount,
        ]);
    }

    // ---- POST /api/process --------------------------------------------------

    public function process(Request $request, Response $response): Response
    {
        $body       = (array) ($request->getParsedBody() ?? []);
        $tenantId   = (int) ($body['tenant_id'] ?? 0);
        $propertyId = (int) ($body['property_id'] ?? 0);
        $periodStr  = (string) ($body['period'] ?? '');
        $paidAtStr  = (string) ($body['paid_at'] ?? '');
        $dryRun     = (bool) ($body['dry_run'] ?? false);
        $resend     = (bool) ($body['resend'] ?? false);
        $rearchive  = (bool) ($body['rearchive'] ?? false);

        if (!$tenantId || !$propertyId) {
            return $this->error($response, 'tenant_id et property_id sont requis');
        }

        $tz     = new \DateTimeZone('Europe/Paris');
        $period = $periodStr !== '' ? $periodStr : Month::current($tz)->toString();
        try {
            $paidAt = $paidAtStr !== '' ? new \DateTimeImmutable($paidAtStr, $tz) : new \DateTimeImmutable('today', $tz);
        } catch (\Throwable) {
            $paidAt = new \DateTimeImmutable('today', $tz);
        }

        try {
            $result = $this->processUseCase->execute($tenantId, $propertyId, [
                'period'    => $period,
                'paid_at'   => $paidAt,
                'dry_run'   => $dryRun,
                'resend'    => $resend,
                'rearchive' => $rearchive,
            ]);

            return $this->json($response, [
                'input'   => $result->input,
                'payment' => $result->payment,
                'receipt' => $result->receipt,
                'email'   => $result->email,
                'archive' => $result->archive,
                'result'  => [
                    'ok'             => empty($result->errors),
                    'warnings'       => 0,
                    'errors'         => count($result->errors),
                    'error_messages' => $result->errors,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, [
                'result' => [
                    'ok' => false, 'warnings' => 0, 'errors' => 1,
                    'error_messages' => [$e->getMessage()],
                ],
            ], 500);
        }
    }

    // ---- GET /api/env-check -------------------------------------------------

    public function envCheck(Request $request, Response $response): Response
    {
        $checks = [];

        // Database
        try {
            $this->owners->listAll(); // simple connectivity probe
            $checks[] = [
                'name'   => 'Base de données',
                'icon'   => 'payments',
                'detail' => 'SQLite · database.sqlite',
                'state'  => 'ok',
                'value'  => 'Connexion établie',
            ];
        } catch (\Throwable $e) {
            $checks[] = [
                'name'   => 'Base de données',
                'icon'   => 'payments',
                'detail' => 'SQLite · database.sqlite',
                'state'  => 'err',
                'value'  => $e->getMessage(),
            ];
        }

        // SMTP
        $smtpHost = (string) ($this->config['smtp']['host'] ?? '');
        $smtpPort = (string) ($this->config['smtp']['port'] ?? '');
        $smtpUser = (string) ($this->config['smtp']['username'] ?? '');
        $smtpOk   = $smtpHost !== '' && $smtpHost !== 'smtp.example.com' && $smtpUser !== '';
        $checks[] = [
            'name'   => 'Serveur SMTP',
            'icon'   => 'mail',
            'detail' => $smtpHost !== '' ? "{$smtpHost}:{$smtpPort}" : 'Non configuré',
            'state'  => $smtpOk ? 'ok' : 'warn',
            'value'  => $smtpOk ? 'Configuré' : 'Variables manquantes ou par défaut',
        ];

        // Nextcloud
        $ncUrl  = (string) ($this->config['nextcloud']['base_url'] ?? '');
        $ncUser = (string) ($this->config['nextcloud']['username'] ?? '');
        $ncOk   = $ncUrl !== '' && $ncUser !== '';
        $checks[] = [
            'name'   => 'Nextcloud (WebDAV)',
            'icon'   => 'cloud',
            'detail' => $ncUrl !== '' ? $ncUrl : 'Non configuré',
            'state'  => $ncOk ? 'ok' : 'warn',
            'value'  => $ncOk ? 'Configuré' : 'Variables manquantes',
        ];

        // wkhtmltopdf
        $wkBin    = (string) ($this->config['pdf']['wkhtmltopdf_binary'] ?? 'wkhtmltopdf');
        $wkExists = is_executable($wkBin)
            || !empty(shell_exec('which ' . escapeshellarg($wkBin) . ' 2>/dev/null'));
        $checks[] = [
            'name'   => 'wkhtmltopdf',
            'icon'   => 'file',
            'detail' => $wkBin,
            'state'  => $wkExists ? 'ok' : 'err',
            'value'  => $wkExists ? 'Disponible' : 'Introuvable dans PATH',
        ];

        return $this->json($response, $checks);
    }

    // ---- GET /api/receipts/{id}/pdf ----------------------------------------

    public function serveReceiptPdf(Request $request, Response $response, array $args): Response
    {
        $receipt = $this->receipts->findOneDetailed((int) $args['id']);
        if ($receipt === null || empty($receipt['pdf_path']) || !file_exists($receipt['pdf_path'])) {
            return $this->json($response, ['error' => 'Quittance introuvable'], 404);
        }

        $filename = basename($receipt['pdf_path']);
        $response->getBody()->write((string) file_get_contents($receipt['pdf_path']));
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . $filename . '"');
    }
}
