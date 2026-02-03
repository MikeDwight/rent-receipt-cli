<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Database;

use InvalidArgumentException;
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
            throw new InvalidArgumentException('Missing "rent_payment_id" for receipt creation.');
        }
        if (!isset($data['pdf_path'])) {
            throw new InvalidArgumentException('Missing "pdf_path" for receipt creation.');
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByMonth(Month $month): array
    {
        $sql = <<<SQL
SELECT
    r.id,
    r.rent_payment_id,
    r.pdf_path,
    r.sent_at,
    r.send_error,
    r.archived_at,
    r.archive_path,
    r.archive_error,
    rp.period,
    rp.tenant_id
FROM receipts r
JOIN rent_payments rp ON rp.id = r.rent_payment_id
WHERE rp.period = :period
ORDER BY r.id ASC
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['period' => $month->toString()]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    /**
 * @return array<int, array<string, mixed>>
 */
public function findPendingByMonth(Month $month): array
{
    $sql = <<<SQL
SELECT
    r.id,
    r.rent_payment_id,
    r.pdf_path,
    r.sent_at,
    r.send_error,
    r.archived_at,
    r.archive_path,
    r.archive_error,
    rp.period,
    rp.tenant_id,
    t.email AS tenant_email,
    t.full_name AS tenant_name
FROM receipts r
JOIN rent_payments rp ON rp.id = r.rent_payment_id
JOIN tenants t ON t.id = rp.tenant_id
WHERE rp.period = :period
  AND r.sent_at IS NULL
AND (r.send_error IS NULL OR r.send_error != '')

ORDER BY r.id ASC
SQL;

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['period' => $month->toString()]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function markSent(int $receiptId, ?string $errorMessage): void
{
    if ($errorMessage === null) {
        $sql = "UPDATE receipts SET sent_at = datetime('now'), send_error = NULL WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $receiptId]);
        return;
    }

    $sql = "UPDATE receipts SET send_error = :err WHERE id = :id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['id' => $receiptId, 'err' => $errorMessage]);
}

public function markArchived(int $receiptId, ?string $archivedPath, ?string $errorMessage): void
{
    if ($errorMessage === null && $archivedPath !== null) {
        $sql = "UPDATE receipts SET archived_at = datetime('now'), archive_path = :path, archive_error = NULL WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $receiptId, 'path' => $archivedPath]);
        return;
    }

    $sql = "UPDATE receipts SET archive_error = :err WHERE id = :id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['id' => $receiptId, 'err' => $errorMessage ?? 'archive failed']);
}

}
