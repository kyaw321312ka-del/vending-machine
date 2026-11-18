<?php

declare(strict_types=1);

namespace App\Contracts;

interface TransactionRepositoryInterface
{
    public function create(
        int $userId,
        int $productId,
        int $quantity,
        string $unitPrice,
        string $totalPrice
    ): int;

    public function findById(int $id): ?array;

    public function getByUserId(int $userId, int $limit = 10, int $offset = 0): array;

    public function getAll(int $limit = 10, int $offset = 0): array;

    public function countAll(): int;

    public function countByUserId(int $userId): int;
}
