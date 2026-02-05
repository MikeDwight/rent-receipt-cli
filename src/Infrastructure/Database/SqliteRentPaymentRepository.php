<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Database;

use PDO;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;

final class SqliteRentPaymentRepository implements RentPaymentRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findForMonth(Month $month): array
    {
        $sql = <<<SQL
        SELECT
            rp.id AS rent_payment_id,
            rp.tenant_id,
            rp.property_id,
            rp.period AS month,
            rp.rent_amount AS rent_amount,
            rp.charges_amount AS charges_amount,
            rp.paid_at AS paid_at,

            t.full_name  AS tenant_name,
            t.email AS tenant_email,
            t.address AS tenant_address,

            p.label AS property_label,
            p.address AS property_address
        FROM rent_payments rp
        JOIN tenants t ON t.id = rp.tenant_id
        JOIN properties p ON p.id = rp.property_id
        WHERE rp.period = :month
        ORDER BY rp.tenant_id ASC
        SQL;



        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['month' => $month->toString()]);

        return $stmt->fetchAll();
    }

    public function list(
        ?Month $month = null,
        ?int $tenantId = null,
        ?int $propertyId = null
    ): array {
        $sql = <<<SQL
        SELECT
            id,
            tenant_id,
            property_id,
            period,
            rent_amount,
            charges_amount,
            paid_at,
            created_at
        FROM rent_payments
        SQL;

        $conditions = [];
        $params = [];

        if ($month !== null) {
            $conditions[] = 'period = :period';
            $params[':period'] = $month->toString();
        }

        if ($tenantId !== null) {
            $conditions[] = 'tenant_id = :tenant_id';
            $params[':tenant_id'] = $tenantId;
        }

        if ($propertyId !== null) {
            $conditions[] = 'property_id = :property_id';
            $params[':property_id'] = $propertyId;
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY period ASC, tenant_id ASC, property_id ASC, id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        /** @var list<array{
         *   id:string|int,
         *   tenant_id:string|int,
         *   property_id:string|int,
         *   period:string,
         *   rent_amount:string|int,
         *   charges_amount:string|int,
         *   paid_at:string,
         *   created_at:string
         * }> $rows
         */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['tenant_id'] = (int) $row['tenant_id'];
            $row['property_id'] = (int) $row['property_id'];
            $row['rent_amount'] = (int) $row['rent_amount'];
            $row['charges_amount'] = (int) $row['charges_amount'];
        }

        return $rows;
    }

    public function findById(int $id): ?array
    {
        $sql = <<<SQL
        SELECT
            id,
            tenant_id,
            property_id,
            period,
            rent_amount,
            charges_amount,
            paid_at,
            created_at
        FROM rent_payments
        WHERE id = :id
        LIMIT 1
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['tenant_id'] = (int) $row['tenant_id'];
        $row['property_id'] = (int) $row['property_id'];
        $row['rent_amount'] = (int) $row['rent_amount'];
        $row['charges_amount'] = (int) $row['charges_amount'];

        return $row;
    }

    public function create(
        int $tenantId,
        int $propertyId,
        Month $period,
        int $rentAmount,
        int $chargesAmount,
        \DateTimeImmutable $paidAt
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rent_payments (
                tenant_id,
                property_id,
                period,
                rent_amount,
                charges_amount,
                paid_at,
                created_at
            ) VALUES (
                :tenant_id,
                :property_id,
                :period,
                :rent_amount,
                :charges_amount,
                :paid_at,
                datetime('now')
            )"
        );

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':property_id' => $propertyId,
            ':period' => $period->toString(),
            ':rent_amount' => $rentAmount,
            ':charges_amount' => $chargesAmount,
            ':paid_at' => $paidAt->format('Y-m-d'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(
        int $id,
        int $tenantId,
        int $propertyId,
        Month $period,
        int $rentAmount,
        int $chargesAmount,
        \DateTimeImmutable $paidAt
    ): void {
        $stmt = $this->pdo->prepare(
            "UPDATE rent_payments
             SET tenant_id = :tenant_id,
                 property_id = :property_id,
                 period = :period,
                 rent_amount = :rent_amount,
                 charges_amount = :charges_amount,
                 paid_at = :paid_at
             WHERE id = :id"
        );

        $stmt->execute([
            ':id' => $id,
            ':tenant_id' => $tenantId,
            ':property_id' => $propertyId,
            ':period' => $period->toString(),
            ':rent_amount' => $rentAmount,
            ':charges_amount' => $chargesAmount,
            ':paid_at' => $paidAt->format('Y-m-d'),
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM rent_payments WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}
