<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UserRepository;
use App\Services\AuthService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthServiceTest extends TestCase
{
    private UserRepository $users;
    private AuthService $service;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

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
        $this->users = new UserRepository($pdo);
        $this->service = new AuthService($this->users);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function testRegistrationNormalizesEmailHashesPasswordAndUsesUserRole(): void
    {
        $id = $this->service->register(' Ada Lovelace ', ' ADA@example.com ', 'password-123');
        $storedUser = $this->users->findByEmail('ada@example.com');

        self::assertSame('Ada Lovelace', $storedUser['name']);
        self::assertSame('user', $storedUser['role']);
        self::assertNotSame('password-123', $storedUser['password']);
        self::assertTrue(password_verify('password-123', $storedUser['password']));
        self::assertSame($id, (int) $storedUser['id']);
    }

    public function testRegistrationRejectsInvalidInputAndDuplicateEmail(): void
    {
        try {
            $this->service->register('   ', 'valid@example.com', 'password-123');
            self::fail('Expected an empty name to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Name is required.', $exception->getMessage());
        }

        try {
            $this->service->register('Valid Name', 'invalid-email', 'password-123');
            self::fail('Expected an invalid email to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid email address.', $exception->getMessage());
        }

        try {
            $this->service->register('Valid Name', 'valid@example.com', 'short');
            self::fail('Expected a short password to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Password must be at least 8 characters.', $exception->getMessage());
        }

        $this->service->register('Valid Name', 'valid@example.com', 'password-123');
        $this->expectException(InvalidArgumentException::class);
        $this->service->register('Another Name', 'VALID@example.com', 'password-456');
    }

    public function testLoginReturnsUserDetailsOnlyForValidCredentials(): void
    {
        $this->service->register('Grace Hopper', 'grace@example.com', 'password-123');

        $user = $this->service->attemptLogin(' GRACE@example.com ', 'password-123');
        self::assertSame('Grace Hopper', $user['name']);
        self::assertSame('user', $user['role']);
        self::assertArrayNotHasKey('password', $user);
        self::assertNull($this->service->attemptLogin('grace@example.com', 'wrong-password'));
        self::assertNull($this->service->attemptLogin('invalid-email', 'password-123'));
        self::assertNull($this->service->attemptLogin('unknown@example.com', 'password-123'));
    }

    public function testLoginRequiresAnActiveSession(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->login([
            'id' => 1,
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'role' => 'user',
        ]);
    }

    public function testLoginRegeneratesSessionAndLogoutDestroysIt(): void
    {
        session_id('auth-service-'.bin2hex(random_bytes(8)));
        self::assertTrue(session_start());
        $oldSessionId = session_id();

        $this->service->login([
            'id' => 1,
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'role' => 'user',
            'password' => 'must-not-be-stored',
        ]);

        self::assertNotSame($oldSessionId, session_id());
        self::assertTrue($this->service->isAuthenticated());
        self::assertSame([
            'id' => 1,
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'role' => 'user',
        ], $this->service->currentUser());

        $this->service->logout();

        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertFalse($this->service->isAuthenticated());
    }
}
