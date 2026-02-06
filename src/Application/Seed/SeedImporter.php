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
        // OWNER UPSERT
        // =====================
        $owner = $seed['owner'];

        $stmt = $this->pdo->prepare('SELECT id FROM owners WHERE email = :email');
        $stmt->execute(['email' => $owner['email']]);
        $ownerId = $stmt->fetchColumn();

        if ($ownerId === false) {
            $report->add($dryRun ? 'Owner: would be inserted' : 'Owner: inserted');

            if (!$dryRun) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO owners (full_name, email, address, created_at)
                     VALUES (:full_name, :email, :address, :created_at)'
                );
                $stmt->execute([
                    'full_name'  => $owner['full_name'],
                    'email'      => $owner['email'],
                    'address'    => $owner['address'],
                    'created_at' => $now,
                ]);

                $ownerId = (int) $this->pdo->lastInsertId();
            }
        } else {
            $report->add($dryRun ? 'Owner: would be updated' : 'Owner: updated');

            if (!$dryRun) {
                $stmt = $this->pdo->prepare(
                    'UPDATE owners
                     SET full_name = :full_name,
                         address = :address
                     WHERE email = :email'
                );
                $stmt->execute([
                    'full_name' => $owner['full_name'],
                    'address'   => $owner['address'],
                    'email'     => $owner['email'],
                ]);

                $ownerId = (int) $ownerId;
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
                    'INSERT INTO properties (owner_id, label, address, rent_amount, charges_amount, created_at)
                        VALUES (:owner_id, :label, :address, :rent_amount, :charges_amount, :created_at)'
                );
                $stmt->execute([
                    'owner_id'        => $ownerId,
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
                        SET owner_id = :owner_id,
                            address = :address,
                            rent_amount = :rent_amount,
                            charges_amount = :charges_amount
                        WHERE label = :label'
                );
                $stmt->execute([
                    'owner_id'        => $ownerId,
                    'label'           => $property['label'],
                    'address'         => $property['address'],
                    'rent_amount'     => $property['rent_amount_cents'],
                    'charges_amount'  => $property['charges_amount_cents'],
                ]);   
            }
        }

        return $report;
    }
}
