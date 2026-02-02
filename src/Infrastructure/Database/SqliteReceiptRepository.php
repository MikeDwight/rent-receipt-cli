<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Database;

use PDO;
use RentReceiptCli\Application\Port\ReceiptRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;

final class SqliteReceiptRepository implements ReceiptRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function existsForTenantAndMonth(int $tenantId, Month $month): bool
    {
        $sql = <<<SQL
SELECT 1
FROM receipts r
JOIN rent_payments rp ON rp.id = r.rent_payment_id
WHERE rp.tenant_id = :tenant_id
  AND rp.period = :period
LIMIT 1
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'period' => $month->toString(),
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        if (!isset($data['rent_payment_id'])) {
            throw new \InvalidArgumentException('Missing "rent_payment_id" for receipt creation.');
        }
        if (!isset($data['pdf_path'])) {
            throw new \InvalidArgumentException('Missing "pdf_path" for receipt creation.');
        }

        $sql = <<<SQL
INSERT INTO receipts (rent_payment_id, pdf_path, created_at)
VALUES (:rent_payment_id, :pdf_path, datetime('now'))
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'rent_payment_id' => (int) $data['rent_payment_id'],
            'pdf_path' => (string) $data['pdf_path'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}