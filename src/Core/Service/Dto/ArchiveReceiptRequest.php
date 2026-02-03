<?php

namespace RentReceiptCli\Core\Service\Dto;

final class ArchiveReceiptRequest
{
    public function __construct(
        public readonly string $localPdfPath,
        public readonly string $remotePath
    ) {}
}
