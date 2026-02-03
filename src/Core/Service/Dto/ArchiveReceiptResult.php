<?php

namespace RentReceiptCli\Core\Service\Dto;

final class ArchiveReceiptResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $archivedPath,
        public readonly ?string $errorMessage
    ) {}

    public static function ok(string $archivedPath): self
    {
        return new self(true, $archivedPath, null);
    }

    public static function fail(string $message): self
    {
        return new self(false, null, $message);
    }
}
