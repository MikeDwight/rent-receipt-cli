<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Seed;

use PDO;

final class SeedImporter
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @param array<string, mixed> $seed
     */
    public function import(array $seed, bool $dryRun): ImportReport
    {
        $report = new ImportReport();

        $now = date('c');

        // =====================
        // TENANT UPSERT
        // =====================
        $tenant = $seed['tenant'];

        $stmt = $this->pdo->prepare(
            'SELECT id FROM tenants WHERE email = :email'
        );
        $stmt->execute(['email' => $tenant['email']]);
        $tenantId = $stmt->fetchColumn();

        if ($tenantId === false) {
            $report->add($dryRun
                ? 'Tenant: would be inserted'
                : 'Tenant: inserted'
            );

            if (!$dryRun) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO tenants (full_name, email, address, created_at)
                     VALUES (:full_name, :email, :address, :created_at)'
                );
                $stmt->execute([
                    'full_name'  => $tenant['full_name'],
                    'email'      => $tenant['email'],
                    'address'    => $tenant['address'],
                    'created_at' => $now,
                ]);
            }
        } else {
            $report->add($dryRun
                ? 'Tenant: would be updated'
                : 'Tenant: updated'
            );

            if (!$dryRun) {
                $stmt = $this->pdo->prepare(
                    'UPDATE tenants
                     SET full_name = :full_name,
                         address = :address
                     WHERE email = :email'
                );
                $stmt->execute([
                    'full_name' => $tenant['full_name'],
                    'address'   => $tenant['address'],
                    'email'     => $tenant['email'],
                ]);
            }
        }

        // =====================
        // PROPERTY UPSERT
        // =====================
        $property = $seed['property'];

        $stmt = $this->pdo->prepare(
            'SELECT id FROM properties WHERE label = :label'
        );
        $stmt->execute(['label' => $property['label']]);
        $propertyId = $stmt->fetchColumn();

        if ($propertyId === false) {
            $report->add($dryRun
                ? 'Property: would be inserted'
                : 'Property: inserted'
            );

            if (!$dryRun) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO properties (label, address, rent_amount, charges_amount, created_at)
                     VALUES (:label, :address, :rent_amount, :charges_amount, :created_at)'
                );
                $stmt->execute([
                    'label'           => $property['label'],
                    'address'         => $property['address'],
                    'rent_amount'     => $property['rent_amount_cents'],
                    'charges_amount'  => $property['charges_amount_cents'],
                    'created_at'      => $now,
                ]);
            }
        } else {
            $report->add($dryRun
                ? 'Property: would be updated'
                : 'Property: updated'
            );

            if (!$dryRun) {
                $stmt = $this->pdo->prepare(
                    'UPDATE properties
                     SET address = :address,
                         rent_amount = :rent_amount,
                         charges_amount = :charges_amount
                     WHERE label = :label'
                );
                $stmt->execute([
                    'address'        => $property['address'],
                    'rent_amount'    => $property['rent_amount_cents'],
                    'charges_amount' => $property['charges_amount_cents'],
                    'label'          => $property['label'],
                ]);
            }
        }

        return $report;
    }
}
