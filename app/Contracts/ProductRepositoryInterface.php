<?php

declare(strict_types=1);

namespace App\Contracts;

interface ProductRepositoryInterface
{
    public function create(string $name, string $price, int $quantityAvailable = 0): int;

    public function findById(int $id): ?array;

    public function findByIdForUpdate(int $id): ?array;

    public function getAll(
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'id',
        string $sortDirection = 'ASC'
    ): array;

    public function countAll(): int;

    public function update(int $id, string $name, string $price, int $quantityAvailable): bool;

    public function updateQuantity(int $id, int $quantityAvailable): bool;

    public function delete(int $id): bool;
}
