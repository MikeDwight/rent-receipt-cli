<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Pdf;

use RentReceiptCli\Core\Service\PdfGenerator;

final class WkhtmltopdfPdfGenerator implements PdfGenerator
{
    public function __construct(
        private string $wkhtmltopdfBinary = 'wkhtmltopdf'
    ) {}

    public function generateFromHtml(string $html, string $outputPdfPath): void
    {
        $dir = dirname($outputPdfPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Unable to create directory: {$dir}");
        }

        $tmpHtml = tempnam(sys_get_temp_dir(), 'rr-html-');
        if ($tmpHtml === false) {
            throw new \RuntimeException('Unable to create temp file');
        }

        $tmpHtmlFile = $tmpHtml . '.html';
        rename($tmpHtml, $tmpHtmlFile);

        $bytes = file_put_contents($tmpHtmlFile, $html);
        if ($bytes === false) {
            @unlink($tmpHtmlFile);
            throw new \RuntimeException('Unable to write temp HTML');
        }

        // Important: wkhtmltopdf expects file paths; use escapeshellarg
        $cmd = sprintf(
            '%s %s %s 2>&1',
            escapeshellcmd($this->wkhtmltopdfBinary),
            escapeshellarg('file://' . $tmpHtmlFile),
            escapeshellarg($outputPdfPath)
        );

        exec($cmd, $output, $exitCode);

        @unlink($tmpHtmlFile);

        if ($exitCode !== 0) {
            $message = trim(implode("\n", $output));
            throw new \RuntimeException("wkhtmltopdf failed (exit {$exitCode}): {$message}");
        }

        if (!is_file($outputPdfPath) || filesize($outputPdfPath) === 0) {
            throw new \RuntimeException("PDF not created or empty: {$outputPdfPath}");
        }
    }
}
