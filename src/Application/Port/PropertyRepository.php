<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Port;

interface PropertyRepository
{
    /**
     * @return list<array{id:int, owner_id:int, label:string, address:string, rent_amount:int, charges_amount:int, created_at:string}>
     */
    public function listAll(): array;

    /**
     * @return array{id:int, owner_id:int, label:string, address:string, rent_amount:int, charges_amount:int, created_at:string}|null
     */
    public function findById(int $id): ?array;

    public function create(int $ownerId, string $label, string $address, int $rentAmount, int $chargesAmount): int;

    public function update(int $id, int $ownerId, string $label, string $address, int $rentAmount, int $chargesAmount): void;

    public function countPaymentsForProperty(int $propertyId): int;

    public function delete(int $id): void;

    public function ownerExists(int $ownerId): bool;
}
