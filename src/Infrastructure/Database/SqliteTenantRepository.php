<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Database;

use PDO;
use RentReceiptCli\Application\Port\TenantRepository;

final class SqliteTenantRepository implements TenantRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, full_name, email, address, created_at
             FROM tenants
             ORDER BY id ASC"
        );

        if ($stmt === false) {
            return [];
        }

        /** @var list<array{id:int, full_name:string, email:string, address:string, created_at:string}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
        }

        return $rows;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, full_name, email, address, created_at
             FROM tenants
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        return $row;
    }

    public function create(string $fullName, string $email, string $address): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tenants (full_name, email, address, created_at)
             VALUES (:full_name, :email, :address, datetime('now'))"
        );

        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':address' => $address,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $fullName, string $email, string $address): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tenants
             SET full_name = :full_name,
                 email = :email,
                 address = :address
             WHERE id = :id"
        );

        $stmt->execute([
            ':id' => $id,
            ':full_name' => $fullName,
            ':email' => $email,
            ':address' => $address,
        ]);
    }

    public function countPaymentsForTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM rent_payments WHERE tenant_id = :id;");
        $stmt->execute([':id' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM tenants WHERE id = :id;");
        $stmt->execute([':id' => $id]);
    }
}

