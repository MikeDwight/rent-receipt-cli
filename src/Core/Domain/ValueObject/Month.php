<?php

declare(strict_types=1);

namespace RentReceiptCli\Core\Domain\ValueObject;

use RentReceiptCli\Core\Exception\DomainException;

final class Month
{
    private function __construct(
        private readonly int $year,
        private readonly int $month
    ) {}

    public static function fromString(string $value): self
    {
        // Expected format: YYYY-MM
        if (!preg_match('/^\d{4}-\d{2}$/', $value)) {
            throw new DomainException(sprintf(
                'Invalid month format "%s". Expected YYYY-MM (e.g. 2026-01).',
                $value
            ));
        }

        [$y, $m] = array_map('intval', explode('-', $value));

        if ($m < 1 || $m > 12) {
            throw new DomainException(sprintf(
                'Invalid month value "%s". Month must be between 01 and 12.',
                $value
            ));
        }

        return new self($y, $m);
    }

    public function toString(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function year(): int { return $this->year; }
    public function month(): int { return $this->month; }

    /**
     * Useful for filenames, sorting, etc.
     */
    public function compareTo(self $other): int
    {
        return [$this->year, $this->month] <=> [$other->year, $other->month];
    }
}
