<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Seed;

final class ImportReport
{
    /** @var array<int, string> */
    private array $lines = [];

    public function add(string $line): void
    {
        $this->lines[] = $line;
    }

    /** @return array<int, string> */
    public function all(): array
    {
        return $this->lines;
    }
}