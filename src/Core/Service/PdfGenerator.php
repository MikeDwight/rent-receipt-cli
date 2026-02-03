<?php

declare(strict_types=1);

namespace RentReceiptCli\Core\Service;

use RentReceiptCli\Core\Service\Pdf\PdfOptions;

interface PdfGenerator
{
    /**
     * Generate a PDF file from HTML.
     *
     * @throws \RuntimeException on failure
     */
    public function generateFromHtml(
        string $html,
        string $outputPdfPath,
        ?PdfOptions $options = null
    ): void;
}
