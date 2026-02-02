<?php

declare(strict_types=1);

namespace RentReceiptCli\Core\Service;

interface TemplateRenderer
{
    /**
     * @param array<string, string> $variables
     */
    public function render(string $templatePath, array $variables): string;
}
