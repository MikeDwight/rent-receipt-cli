<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Database;

use PDO;
use RentReceiptCli\Application\Port\PropertyRepository;

final class SqlitePropertyRepository implements PropertyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, owner_id, label, address, rent_amount, charges_amount, created_at
             FROM properties
             ORDER BY id ASC"
        );

        if ($stmt === false) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['owner_id'] = (int) $r['owner_id'];
            $r['rent_amount'] = (int) $r['rent_amount'];
            $r['charges_amount'] = (int) $r['charges_amount'];
        }

        return $rows;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, owner_id, label, address, rent_amount, charges_amount, created_at
             FROM properties
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['owner_id'] = (int) $row['owner_id'];
        $row['rent_amount'] = (int) $row['rent_amount'];
        $row['charges_amount'] = (int) $row['charges_amount'];

        return $row;
    }

    public function create(
        int $ownerId,
        string $label,
        string $address,
        int $rentAmount,
        int $chargesAmount
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO properties (
                owner_id, label, address, rent_amount, charges_amount, created_at
             ) VALUES (
                :owner_id, :label, :address, :rent_amount, :charges_amount, datetime('now')
             )"
        );

        $stmt->execute([
            ':owner_id' => $ownerId,
            ':label' => $label,
            ':address' => $address,
            ':rent_amount' => $rentAmount,
            ':charges_amount' => $chargesAmount,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(
        int $id,
        int $ownerId,
        string $label,
        string $address,
        int $rentAmount,
        int $chargesAmount
    ): void {
        $stmt = $this->pdo->prepare(
            "UPDATE properties
             SET owner_id = :owner_id,
                 label = :label,
                 address = :address,
                 rent_amount = :rent_amount,
                 charges_amount = :charges_amount
             WHERE id = :id"
        );

        $stmt->execute([
            ':id' => $id,
            ':owner_id' => $ownerId,
            ':label' => $label,
            ':address' => $address,
            ':rent_amount' => $rentAmount,
            ':charges_amount' => $chargesAmount,
        ]);
    }

    public function countPaymentsForProperty(int $propertyId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rent_payments WHERE property_id = :id"
        );
        $stmt->execute([':id' => $propertyId]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM properties WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function ownerExists(int $ownerId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM owners WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $ownerId]);

        return $stmt->fetchColumn() !== false;
    }
}
