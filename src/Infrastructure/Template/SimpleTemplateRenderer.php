<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Template;

use RentReceiptCli\Core\Service\TemplateRenderer;

final class SimpleTemplateRenderer implements TemplateRenderer
{
    public function render(string $templatePath, array $variables): string
    {
        if (!is_file($templatePath)) {
            throw new \RuntimeException("Template not found: {$templatePath}");
        }

        $html = file_get_contents($templatePath);
        if ($html === false) {
            throw new \RuntimeException("Unable to read template: {$templatePath}");
        }

        foreach ($variables as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }

        return $html;
    }
}
