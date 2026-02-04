<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Logging;

use RentReceiptCli\Application\Port\Logger;

final class NullLogger implements Logger
{
    public function info(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
    public function error(string $message, array $context = []): void {}
}
