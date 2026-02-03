<?php

namespace RentReceiptCli\Core\Service\Dto;

final class SendReceiptRequest
{
    public function __construct(
        public readonly string $toEmail,
        public readonly string $toName,
        public readonly string $subject,
        public readonly string $bodyText,
        public readonly string $pdfPath
    ) {}
}
