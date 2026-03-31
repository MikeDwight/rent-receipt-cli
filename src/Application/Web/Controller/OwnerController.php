<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RentReceiptCli\Application\Port\OwnerRepository;
use Slim\Views\Twig;

final class OwnerController extends AbstractController
{
    public function __construct(Twig $twig, private readonly OwnerRepository $owners)
    {
        parent::__construct($twig);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'owners/index.html.twig', [
            'owners' => $this->owners->listAll(),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->render($response, 'owners/form.html.twig', ['owner' => null]);
    }

    public function store(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $this->owners->create(
            trim((string) ($body['full_name'] ?? '')),
            trim((string) ($body['email'] ?? '')),
            trim((string) ($body['address'] ?? '')),
        );
        $this->flash('success', 'Bailleur créé.');
        return $this->redirect($response, '/owners');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $owner = $this->owners->findById((int) $args['id']);
        if ($owner === null) {
            return $response->withStatus(404);
        }
        return $this->render($response, 'owners/form.html.twig', ['owner' => $owner]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $this->owners->update(
            (int) $args['id'],
            trim((string) ($body['full_name'] ?? '')),
            trim((string) ($body['email'] ?? '')),
            trim((string) ($body['address'] ?? '')),
        );
        $this->flash('success', 'Bailleur mis à jour.');
        return $this->redirect($response, '/owners');
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($this->owners->countPropertiesForOwner($id) > 0) {
            $this->flash('error', 'Impossible de supprimer : des biens sont liés à ce bailleur.');
            return $this->redirect($response, '/owners');
        }
        $this->owners->delete($id);
        $this->flash('success', 'Bailleur supprimé.');
        return $this->redirect($response, '/owners');
    }
}
