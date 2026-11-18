<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class TransactionRepository
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function create(
        int $userId,
        int $productId,
        int $quantity,
        string $unitPrice,
        string $totalPrice
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO transactions (
                user_id,
                product_id,
                quantity,
                unit_price,
                total_price
            ) VALUES (
                :user_id,
                :product_id,
                :quantity,
                :unit_price,
                :total_price
            )'
        );
        $statement->execute([
            'user_id' => $userId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                t.id,
                t.user_id,
                t.product_id,
                t.quantity,
                t.unit_price,
                t.total_price,
                t.created_at,
                u.name AS user_name,
                p.name AS product_name
            FROM transactions t
            INNER JOIN users u ON u.id = t.user_id
            INNER JOIN products p ON p.id = t.product_id
            WHERE t.id = :id
            LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $transaction = $statement->fetch(PDO::FETCH_ASSOC);

        return $transaction === false ? null : $transaction;
    }

    public function getByUserId(int $userId, int $limit = 10, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $statement = $this->pdo->prepare(
            'SELECT
                t.id,
                t.user_id,
                t.product_id,
                t.quantity,
                t.unit_price,
                t.total_price,
                t.created_at,
                p.name AS product_name
            FROM transactions t
            INNER JOIN products p ON p.id = t.product_id
            WHERE t.user_id = :user_id
            ORDER BY t.created_at DESC, t.id DESC
            LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(int $limit = 10, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $statement = $this->pdo->prepare(
            'SELECT
                t.id,
                t.user_id,
                t.product_id,
                t.quantity,
                t.unit_price,
                t.total_price,
                t.created_at,
                u.name AS user_name,
                p.name AS product_name
            FROM transactions t
            INNER JOIN users u ON u.id = t.user_id
            INNER JOIN products p ON p.id = t.product_id
            ORDER BY t.created_at DESC, t.id DESC
            LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
    }

    public function countByUserId(int $userId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
            FROM transactions
            WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }
}
