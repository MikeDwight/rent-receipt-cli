<?php

namespace RentReceiptCli\Core\Service\Dto;

final class SendReceiptResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage
    ) {}

    public static function ok(): self
    {
        return new self(true, null);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }
}
