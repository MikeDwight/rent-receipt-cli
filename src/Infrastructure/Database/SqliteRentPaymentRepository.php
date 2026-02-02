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
}
