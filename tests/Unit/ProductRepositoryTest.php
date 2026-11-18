<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ProductRepository;
use LogicException;
use PDO;
use PHPUnit\Framework\TestCase;

final class ProductRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ProductRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        $this->repository = new ProductRepository($this->pdo);
    }

    public function testProductCanBeCreatedFoundListedUpdatedAndDeleted(): void
    {
        $id = $this->repository->create('Pepsi', '6.885', 50);

        self::assertSame(1, $id);
        self::assertSame('Pepsi', $this->repository->findById($id)['name']);
        self::assertCount(1, $this->repository->getAll());
        self::assertTrue($this->repository->update($id, 'Pepsi Max', '7.125', 40));
        self::assertSame('Pepsi Max', $this->repository->findById($id)['name']);
        self::assertTrue($this->repository->delete($id));
        self::assertNull($this->repository->findById($id));
    }

    public function testProductNamesAreStoredAsDataRatherThanSql(): void
    {
        $name = "' OR 1=1 --";
        $id = $this->repository->create($name, '1.000');

        self::assertSame($name, $this->repository->findById($id)['name']);
        self::assertCount(1, $this->repository->getAll());
    }

    public function testUpdateAndDeleteReturnFalseForMissingProducts(): void
    {
        self::assertFalse($this->repository->update(99, 'Missing', '1.000', 0));
        self::assertFalse($this->repository->delete(99));
        self::assertNull($this->repository->findById(99));
    }

    public function testFindByIdForUpdateRequiresAnActiveTransaction(): void
    {
        $this->expectException(LogicException::class);

        $this->repository->findByIdForUpdate(1);
    }

    public function testUpdateQuantityChangesOnlyTheAvailableStock(): void
    {
        $id = $this->repository->create('Coke', '3.990', 50);

        self::assertTrue($this->repository->updateQuantity($id, 48));
        self::assertSame(48, (int) $this->repository->findById($id)['quantity_available']);
        self::assertFalse($this->repository->updateQuantity(99, 10));
    }
}
