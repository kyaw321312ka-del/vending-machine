<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\TransactionRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class TransactionRepositoryTest extends TestCase
{
    private PDO $pdo;
    private TransactionRepository $repository;
    private int $userId;
    private int $otherUserId;
    private int $productId;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                price TEXT NOT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                product_id INTEGER NOT NULL REFERENCES products(id),
                quantity INTEGER NOT NULL,
                unit_price TEXT NOT NULL,
                total_price TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $this->userId = $this->createUser('Ada Lovelace');
        $this->otherUserId = $this->createUser('Grace Hopper');
        $this->productId = $this->createProduct('Coke', '3.990');
        $this->repository = new TransactionRepository($this->pdo);
    }

    public function testCreateFindAndPreservePurchaseTimePrices(): void
    {
        $id = $this->repository->create($this->userId, $this->productId, 2, '3.990', '7.980');

        self::assertSame(1, $id);
        $transaction = $this->repository->findById($id);
        self::assertSame('Ada Lovelace', $transaction['user_name']);
        self::assertSame('Coke', $transaction['product_name']);
        self::assertSame('3.990', $transaction['unit_price']);
        self::assertSame('7.980', $transaction['total_price']);

        $updateProduct = $this->pdo->prepare('UPDATE products SET price = :price WHERE id = :id');
        $updateProduct->execute(['price' => '4.500', 'id' => $this->productId]);

        $transaction = $this->repository->findById($id);
        self::assertSame('3.990', $transaction['unit_price']);
        self::assertSame('7.980', $transaction['total_price']);
        self::assertNull($this->repository->findById(999));
    }

    public function testUserHistoryIsFilteredAndPaginated(): void
    {
        $firstId = $this->repository->create($this->userId, $this->productId, 1, '3.990', '3.990');
        $secondId = $this->repository->create($this->userId, $this->productId, 2, '3.990', '7.980');
        $this->repository->create($this->otherUserId, $this->productId, 1, '3.990', '3.990');

        $history = $this->repository->getByUserId($this->userId);
        self::assertSame([$secondId, $firstId], array_column($history, 'id'));
        self::assertSame('Coke', $history[0]['product_name']);
        self::assertSame([$firstId], array_column($this->repository->getByUserId($this->userId, 1, 1), 'id'));
    }

    public function testAllTransactionsArePaginatedAndCounted(): void
    {
        $firstId = $this->repository->create($this->userId, $this->productId, 1, '3.990', '3.990');
        $secondId = $this->repository->create($this->userId, $this->productId, 1, '3.990', '3.990');
        $thirdId = $this->repository->create($this->otherUserId, $this->productId, 1, '3.990', '3.990');

        $page = $this->repository->getAll(2, 1);
        self::assertSame([$secondId, $firstId], array_column($page, 'id'));
        self::assertSame('Grace Hopper', $this->repository->findById($thirdId)['user_name']);
        self::assertSame(3, $this->repository->countAll());
        self::assertSame(2, $this->repository->countByUserId($this->userId));
        self::assertSame(1, $this->repository->countByUserId($this->otherUserId));
    }

    private function createUser(string $name): int
    {
        $statement = $this->pdo->prepare('INSERT INTO users (name) VALUES (:name)');
        $statement->execute(['name' => $name]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createProduct(string $name, string $price): int
    {
        $statement = $this->pdo->prepare('INSERT INTO products (name, price) VALUES (:name, :price)');
        $statement->execute(['name' => $name, 'price' => $price]);

        return (int) $this->pdo->lastInsertId();
    }
}
