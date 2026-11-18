<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use App\Services\PurchaseService;
use DomainException;
use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class PurchaseServiceTest extends TestCase
{
    private PDO $pdo;
    private ProductRepository $products;
    private TransactionRepository $transactions;
    private PurchaseService $service;
    private int $userId;
    private int $cokeId;
    private int $pepsiId;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec(
            "CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'user'
            )"
        );
        $this->pdo->exec(
            'CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                price TEXT NOT NULL,
                quantity_available INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
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

        $insertUser = $this->pdo->prepare(
            'INSERT INTO users (name, email, password) VALUES (:name, :email, :password)'
        );
        $insertUser->execute([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => password_hash('temporary', PASSWORD_DEFAULT),
        ]);
        $this->userId = (int) $this->pdo->lastInsertId();

        $this->products = new ProductRepository($this->pdo);
        $this->transactions = new TransactionRepository($this->pdo);
        $this->cokeId = $this->products->create('Coke', '3.990', 10);
        $this->pepsiId = $this->products->create('Pepsi', '6.885', 50);
        $this->service = new PurchaseService($this->pdo, $this->products, $this->transactions);
    }

    public function testSuccessfulPurchaseDeductsStockAndRecordsPurchase(): void
    {
        $result = $this->service->purchase($this->userId, $this->cokeId, 2);

        self::assertSame('Coke', $result['product_name']);
        self::assertSame('3.990', $result['unit_price']);
        self::assertSame('7.980', $result['total_price']);
        self::assertSame(8, $result['remaining_quantity']);
        self::assertSame(8, (int) $this->products->findById($this->cokeId)['quantity_available']);
        self::assertSame('7.980', $this->transactions->findById($result['transaction_id'])['total_price']);
    }

    public function testPepsiPurchaseUsesExactDecimalArithmetic(): void
    {
        $result = $this->service->purchase($this->userId, $this->pepsiId, 2);

        self::assertSame('6.885', $result['unit_price']);
        self::assertSame('13.770', $result['total_price']);
        self::assertSame(48, $result['remaining_quantity']);
    }

    public function testMissingProductDoesNotChangeInventoryOrCreateTransaction(): void
    {
        try {
            $this->service->purchase($this->userId, 999, 1);
            self::fail('Expected a missing product to be rejected.');
        } catch (DomainException $exception) {
            self::assertSame('Product not found.', $exception->getMessage());
        }

        self::assertSame(10, (int) $this->products->findById($this->cokeId)['quantity_available']);
        self::assertSame(0, $this->transactions->countAll());
    }

    public function testInsufficientStockDoesNotChangeInventoryOrCreateTransaction(): void
    {
        try {
            $this->service->purchase($this->userId, $this->cokeId, 11);
            self::fail('Expected insufficient inventory to be rejected.');
        } catch (DomainException $exception) {
            self::assertSame('Insufficient product inventory.', $exception->getMessage());
        }

        self::assertSame(10, (int) $this->products->findById($this->cokeId)['quantity_available']);
        self::assertSame(0, $this->transactions->countAll());
    }

    public function testZeroQuantityIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->purchase($this->userId, $this->cokeId, 0);
    }

    public function testNegativeQuantityIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->purchase($this->userId, $this->cokeId, -1);
    }

    public function testInvalidUserIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->purchase(0, $this->cokeId, 1);
    }

    public function testTransactionInsertFailureRollsBackInventoryUpdate(): void
    {
        $this->pdo->exec(
            "CREATE TRIGGER fail_transaction_insert
            BEFORE INSERT ON transactions
            BEGIN
                SELECT RAISE(ABORT, 'transaction insert failed');
            END"
        );

        try {
            $this->service->purchase($this->userId, $this->cokeId, 2);
            self::fail('Expected transaction insertion to fail.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('transaction insert failed', $exception->getMessage());
        }

        self::assertFalse($this->pdo->inTransaction());
        self::assertSame(10, (int) $this->products->findById($this->cokeId)['quantity_available']);
        self::assertSame(0, $this->transactions->countAll());
    }

    public function testEntireStockCanBePurchased(): void
    {
        $result = $this->service->purchase($this->userId, $this->cokeId, 10);

        self::assertSame(0, $result['remaining_quantity']);
        self::assertSame(0, (int) $this->products->findById($this->cokeId)['quantity_available']);
        self::assertSame('39.900', $result['total_price']);
    }
}
