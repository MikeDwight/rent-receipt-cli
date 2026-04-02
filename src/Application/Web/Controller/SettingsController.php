<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RentReceiptCli\Infrastructure\Database\SqliteSettingsRepository;
use Slim\Views\Twig;

final class SettingsController extends AbstractController
{
    public function __construct(Twig $twig, private readonly SqliteSettingsRepository $settings)
    {
        parent::__construct($twig);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'settings/index.html.twig', [
            'nextcloud_target_dir' => $this->settings->get('nextcloud_target_dir'),
        ]);
    }

    public function update(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $this->settings->set('nextcloud_target_dir', trim((string) ($body['nextcloud_target_dir'] ?? '')));
        $this->flash('success', 'Paramètres enregistrés.');
        return $this->redirect($response, '/settings');
    }
}
