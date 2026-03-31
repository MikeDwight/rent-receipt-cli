<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RentReceiptCli\Application\Port\OwnerRepository;
use RentReceiptCli\Application\Port\PropertyRepository;
use Slim\Views\Twig;

final class PropertyController extends AbstractController
{
    public function __construct(
        Twig $twig,
        private readonly PropertyRepository $properties,
        private readonly OwnerRepository $owners,
    ) {
        parent::__construct($twig);
    }

    public function index(Request $request, Response $response): Response
    {
        $properties = $this->properties->listAll();
        $owners = array_column($this->owners->listAll(), null, 'id');
        return $this->render($response, 'properties/index.html.twig', [
            'properties' => $properties,
            'owners'     => $owners,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->render($response, 'properties/form.html.twig', [
            'property' => null,
            'owners'   => $this->owners->listAll(),
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $this->properties->create(
            (int) ($body['owner_id'] ?? 0),
            trim((string) ($body['label'] ?? '')),
            trim((string) ($body['address'] ?? '')),
            (int) round((float) ($body['rent_amount'] ?? 0) * 100),
            (int) round((float) ($body['charges_amount'] ?? 0) * 100),
        );
        $this->flash('success', 'Bien créé.');
        return $this->redirect($response, '/properties');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $property = $this->properties->findById((int) $args['id']);
        if ($property === null) {
            return $response->withStatus(404);
        }
        return $this->render($response, 'properties/form.html.twig', [
            'property' => $property,
            'owners'   => $this->owners->listAll(),
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $this->properties->update(
            (int) $args['id'],
            (int) ($body['owner_id'] ?? 0),
            trim((string) ($body['label'] ?? '')),
            trim((string) ($body['address'] ?? '')),
            (int) round((float) ($body['rent_amount'] ?? 0) * 100),
            (int) round((float) ($body['charges_amount'] ?? 0) * 100),
        );
        $this->flash('success', 'Bien mis à jour.');
        return $this->redirect($response, '/properties');
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($this->properties->countPaymentsForProperty($id) > 0) {
            $this->flash('error', 'Impossible de supprimer : des paiements sont liés à ce bien.');
            return $this->redirect($response, '/properties');
        }
        $this->properties->delete($id);
        $this->flash('success', 'Bien supprimé.');
        return $this->redirect($response, '/properties');
    }
}
