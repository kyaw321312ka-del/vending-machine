<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use InvalidArgumentException;
use LogicException;
use PDO;

final class ProductRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function create(string $name, string $price, int $quantityAvailable = 0): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (name, price, quantity_available)
            VALUES (:name, :price, :quantity_available)'
        );
        $statement->execute([
            'name' => $name,
            'price' => $price,
            'quantity_available' => $quantityAvailable,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, price, quantity_available, created_at, updated_at
            FROM products
            WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $product = $statement->fetch(PDO::FETCH_ASSOC);

        return $product === false ? null : $product;
    }

    public function findByIdForUpdate(int $id): ?array
    {
        if (!$this->pdo->inTransaction()) {
            throw new LogicException('A database transaction is required to lock a product.');
        }

        $sql = 'SELECT id, name, price, quantity_available
            FROM products
            WHERE id = :id';
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        $product = $statement->fetch(PDO::FETCH_ASSOC);

        return $product === false ? null : $product;
    }

    public function getAll(
        int $limit = 10,
        int $offset = 0,
        string $sortBy = 'id',
        string $sortDirection = 'ASC'
    ): array {
        $sortColumns = [
            'id' => 'id',
            'name' => 'name',
            'price' => 'price',
            'quantity_available' => 'quantity_available',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        if (!isset($sortColumns[$sortBy])) {
            throw new InvalidArgumentException('Unsupported product sort column.');
        }

        $sortDirection = strtoupper($sortDirection);
        if (!in_array($sortDirection, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Sort direction must be ASC or DESC.');
        }

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $statement = $this->pdo->prepare(
            'SELECT id, name, price, quantity_available, created_at, updated_at
            FROM products
            ORDER BY '.$sortColumns[$sortBy].' '.$sortDirection.'
            LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    }

    public function update(int $id, string $name, string $price, int $quantityAvailable): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE products
            SET name = :name, price = :price, quantity_available = :quantity_available
            WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'price' => $price,
            'quantity_available' => $quantityAvailable,
        ]);

        return $statement->rowCount() > 0 || $this->findById($id) !== null;
    }

    public function updateQuantity(int $id, int $quantityAvailable): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE products
            SET quantity_available = :quantity
            WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'quantity' => $quantityAvailable,
        ]);

        return $statement->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }
}
