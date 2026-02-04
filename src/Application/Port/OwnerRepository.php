<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Port;

interface OwnerRepository
{
    /**
     * @return list<array{id:int, full_name:string, email:string, address:string, created_at:string}>
     */
    public function listAll(): array;

    /**
     * @return array{id:int, full_name:string, email:string, address:string, created_at:string}|null
     */
    public function findById(int $id): ?array;

    public function create(string $fullName, string $email, string $address): int;

    public function update(int $id, string $fullName, string $email, string $address): void;
}
