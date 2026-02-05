<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Logging;

use RentReceiptCli\Application\Port\Logger;

final class FileLogger implements Logger
{
    public function __construct(
        private readonly string $logDir,
        private readonly string $level = 'info'
    ) {
        if (!is_dir($this->logDir) && !mkdir($this->logDir, 0775, true) && !is_dir($this->logDir)) {
            // Fallback silencieux mais non bloquant : on loguera éventuellement vers error_log si l'écriture de fichier échoue.
            error_log('FileLogger: cannot create log directory: ' . $this->logDir);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $line = $this->formatLine($level, $message, $context);
        $file = ($level === 'error') ? 'error.log' : 'app.log';

        $path = $this->logDir . DIRECTORY_SEPARATOR . $file;
        $result = @file_put_contents($path, $line . PHP_EOL, FILE_APPEND);

        if ($result === false) {
            error_log('FileLogger: failed to write log file: ' . $path);
        }
    }

    private function shouldLog(string $level): bool
    {
        $rank = ['info' => 10, 'warning' => 20, 'error' => 30];
        return ($rank[$level] ?? 10) >= ($rank[$this->level] ?? 10);
    }

    private function formatLine(string $level, string $message, array $context): string
    {
        $ts = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        $ctx = '';
        if ($context !== []) {
            $parts = [];
            foreach ($context as $k => $v) {
                if (is_scalar($v) || $v === null) {
                    $parts[] = $k . '=' . (string) $v;
                } else {
                    $parts[] = $k . '=' . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            $ctx = ' ' . implode(' ', $parts);
        }

        return sprintf('%s [%s] %s%s', $ts, $level, $message, $ctx);
    }
}
