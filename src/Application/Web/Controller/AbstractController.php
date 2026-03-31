<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

abstract class AbstractController
{
    public function __construct(protected Twig $twig) {}

    protected function render(Response $response, string $template, array $data = []): Response
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $this->twig->render($response, $template, array_merge(['flash' => $flash], $data));
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    protected function redirect(Response $response, string $url): Response
    {
        return $response->withHeader('Location', $url)->withStatus(302);
    }
}
