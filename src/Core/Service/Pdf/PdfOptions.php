<?php

declare(strict_types=1);

namespace RentReceiptCli\Core\Service\Pdf;

final class PdfOptions
{
    public function __construct(
        public readonly string $pageSize = 'A4',
        public readonly string $orientation = 'Portrait', // Portrait | Landscape
        public readonly int $marginTopMm = 10,
        public readonly int $marginRightMm = 10,
        public readonly int $marginBottomMm = 10,
        public readonly int $marginLeftMm = 10,
        public readonly bool $enableLocalFileAccess = true,
    ) {
    }
}
