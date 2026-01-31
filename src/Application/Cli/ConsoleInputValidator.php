<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Cli;

final class ConsoleInputValidator
{
    public static function isValidMonth(string $month): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month);
    }
}
