<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Database;

use PDO;

final class PdoConnectionFactory
{
    public function __construct(private readonly string $sqlitePath) {}

    public function create(): PDO
    {
        $pdo = new PDO('sqlite:' . $this->sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Recommended for SQLite FK constraints
        $pdo->exec('PRAGMA foreign_keys = ON;');

        return $pdo;
    }
}
