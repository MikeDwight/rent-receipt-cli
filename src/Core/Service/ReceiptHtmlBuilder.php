<?php

declare(strict_types=1);

namespace RentReceiptCli\Core\Service;

final class ReceiptHtmlBuilder
{
    public function __construct(
        private TemplateRenderer $renderer,
        private string $templatePath,
    ) {}

    /**
     * @param array<string, string> $variables
     */
    public function build(array $variables): string
    {
        return $this->renderer->render($this->templatePath, $variables);
    }
}
