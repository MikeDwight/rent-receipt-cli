<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Database;

use PDO;
use RuntimeException;

final class SqliteMigrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationsDir
    ) {
    }

    public function ensureMigrationsTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL
            );"
        );
    }

    /**
     * @return list<string> migration filenames (e.g. 2026_02_03_000001_xxx.sql)
     */
    public function listAllMigrations(): array
    {
        if (!is_dir($this->migrationsDir)) {
            throw new RuntimeException("Migrations dir not found: {$this->migrationsDir}");
        }

        $files = glob(rtrim($this->migrationsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        $files = array_filter($files, static fn (string $path): bool => is_file($path));
        sort($files, SORT_STRING);

        // return basenames for stable tracking
        return array_values(array_map('basename', $files));
    }

    /**
     * @return array<string, string> map[version] = applied_at
     */
    public function listAppliedMigrations(): array
    {
        $this->ensureMigrationsTable();

        $stmt = $this->pdo->query("SELECT version, applied_at FROM schema_migrations ORDER BY version ASC;");
        if ($stmt === false) {
            throw new RuntimeException('Failed to query schema_migrations.');
        }

        /** @var array<string, string> $out */
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[(string) $row['version']] = (string) $row['applied_at'];
        }

        return $out;
    }

    /**
     * @return list<string> pending migration versions
     */
    public function listPendingMigrations(): array
    {
        $all = $this->listAllMigrations();
        $applied = $this->listAppliedMigrations();

        return array_values(array_filter(
            $all,
            static fn (string $v): bool => !array_key_exists($v, $applied)
        ));
    }

    /**
     * Applies a single migration inside a transaction.
     */
    public function apply(string $version): void
    {
        $this->ensureMigrationsTable();

        $path = rtrim($this->migrationsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $version;
        if (!is_file($path)) {
            throw new RuntimeException("Migration file not found: {$path}");
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException("Failed to read migration: {$path}");
        }

        // Capture current FK setting for this connection
        $fkStmt = $this->pdo->query("PRAGMA foreign_keys;");
        $currentFk = $fkStmt ? (int) $fkStmt->fetchColumn() : 0;

        // Disable FK enforcement during migration (needed for table rebuilds)
        $this->pdo->exec("PRAGMA foreign_keys = OFF;");

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec($sql);

            $stmt = $this->pdo->prepare(
                "INSERT INTO schema_migrations (version, applied_at) VALUES (:version, datetime('now'));"
            );
            if ($stmt === false) {
                throw new RuntimeException('Failed to prepare schema_migrations insert.');
            }
            $stmt->execute([':version' => $version]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            // Restore FK before throwing
            $this->pdo->exec("PRAGMA foreign_keys = " . ($currentFk ? "ON" : "OFF") . ";");
            throw new RuntimeException("Migration failed ({$version}): " . $e->getMessage(), 0, $e);
        }

        // Restore FK enforcement to previous state
        $this->pdo->exec("PRAGMA foreign_keys = " . ($currentFk ? "ON" : "OFF") . ";");
    }

}
