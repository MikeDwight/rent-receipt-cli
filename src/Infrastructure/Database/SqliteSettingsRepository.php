<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Database;

final class SqliteSettingsRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? (string) $row['value'] : $default;
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        );
        $stmt->execute([$key, $value]);
    }
}
