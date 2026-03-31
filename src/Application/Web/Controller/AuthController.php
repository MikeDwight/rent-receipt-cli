<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AuthController extends AbstractController
{
    public function __construct(Twig $twig, private readonly string $passwordHash) {
        parent::__construct($twig);
    }

    public function showLogin(Request $request, Response $response): Response
    {
        if (!empty($_SESSION['authenticated'])) {
            return $this->redirect($response, '/');
        }
        return $this->render($response, 'auth/login.html.twig');
    }

    public function handleLogin(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $password = (string) ($body['password'] ?? '');

        $valid = $this->passwordHash !== '' && (
            str_starts_with($this->passwordHash, '$2y$')
                ? password_verify($password, $this->passwordHash)
                : hash_equals($this->passwordHash, $password)
        );

        if ($valid) {
            $_SESSION['authenticated'] = true;
            return $this->redirect($response, '/');
        }

        $this->flash('error', 'Mot de passe incorrect.');
        return $this->redirect($response, '/login');
    }

    public function handleLogout(Request $request, Response $response): Response
    {
        $_SESSION = [];
        session_destroy();
        return $this->redirect($response, '/login');
    }
}
