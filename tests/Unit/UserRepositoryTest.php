<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserRepositoryTest extends TestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('admin', 'user')),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->repository = new UserRepository($pdo);
    }

    public function testCreateAndLookupKeepPasswordHashOutOfIdLookup(): void
    {
        $passwordHash = password_hash('correct horse battery staple', PASSWORD_DEFAULT);
        $id = $this->repository->create('Ada Lovelace', 'ada@example.com', $passwordHash);

        self::assertSame(1, $id);
        self::assertTrue($this->repository->emailExists('ada@example.com'));
        self::assertFalse($this->repository->emailExists('missing@example.com'));
        self::assertSame('user', $this->repository->findById($id)['role']);
        self::assertArrayNotHasKey('password', $this->repository->findById($id));
        self::assertSame($passwordHash, $this->repository->findByEmail('ada@example.com')['password']);
        self::assertNull($this->repository->findById(99));
        self::assertNull($this->repository->findByEmail('missing@example.com'));
    }

    public function testPasswordAndRoleCanBeUpdated(): void
    {
        $id = $this->repository->create('Grace Hopper', 'grace@example.com', 'old-hash');

        self::assertTrue($this->repository->updatePassword($id, 'new-hash'));
        self::assertSame('new-hash', $this->repository->findByEmail('grace@example.com')['password']);
        self::assertTrue($this->repository->updateRole($id, 'admin'));
        self::assertSame('admin', $this->repository->findById($id)['role']);
        self::assertFalse($this->repository->updatePassword(99, 'unused-hash'));
        self::assertFalse($this->repository->updateRole(99, 'admin'));
    }

    public function testCreateUsesBoundValuesForEmail(): void
    {
        $email = "' OR 1=1 --";
        $id = $this->repository->create('Test User', $email, 'hash');

        self::assertSame($email, $this->repository->findByEmail($email)['email']);
        self::assertFalse($this->repository->emailExists('other@example.com'));
        self::assertSame(1, $id);
    }
}
