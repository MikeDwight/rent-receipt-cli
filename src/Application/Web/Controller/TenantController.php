<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RentReceiptCli\Application\Port\TenantRepository;
use Slim\Views\Twig;

final class TenantController extends AbstractController
{
    public function __construct(Twig $twig, private readonly TenantRepository $tenants)
    {
        parent::__construct($twig);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'tenants/index.html.twig', [
            'tenants' => $this->tenants->listAll(),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->render($response, 'tenants/form.html.twig', ['tenant' => null]);
    }

    public function store(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $this->tenants->create(
            trim((string) ($body['full_name'] ?? '')),
            trim((string) ($body['email'] ?? '')),
            trim((string) ($body['address'] ?? '')),
        );
        $this->flash('success', 'Locataire créé.');
        return $this->redirect($response, '/tenants');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $tenant = $this->tenants->findById((int) $args['id']);
        if ($tenant === null) {
            return $response->withStatus(404);
        }
        return $this->render($response, 'tenants/form.html.twig', ['tenant' => $tenant]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $this->tenants->update(
            (int) $args['id'],
            trim((string) ($body['full_name'] ?? '')),
            trim((string) ($body['email'] ?? '')),
            trim((string) ($body['address'] ?? '')),
        );
        $this->flash('success', 'Locataire mis à jour.');
        return $this->redirect($response, '/tenants');
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($this->tenants->countPaymentsForTenant($id) > 0) {
            $this->flash('error', 'Impossible de supprimer : des paiements sont liés à ce locataire.');
            return $this->redirect($response, '/tenants');
        }
        $this->tenants->delete($id);
        $this->flash('success', 'Locataire supprimé.');
        return $this->redirect($response, '/tenants');
    }
}
