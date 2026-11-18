<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $authMiddleware;
    private RoleMiddleware $roleMiddleware;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        session_id('middleware-test-'.bin2hex(random_bytes(8)));
        self::assertTrue(session_start());
        $_SESSION = [];
        $this->authMiddleware = new AuthMiddleware();
        $this->roleMiddleware = new RoleMiddleware();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function testGuestIsRejectedByAuthAndRoleMiddleware(): void
    {
        [$authResult, $authResponse, $authStatus] = $this->call(
            fn (): bool => $this->authMiddleware->handle()
        );
        self::assertFalse($authResult);
        self::assertSame(401, $authStatus);
        self::assertSame('Authentication required.', $authResponse['message']);

        [$roleResult, $roleResponse, $roleStatus] = $this->call(
            fn (): bool => $this->roleMiddleware->handle('admin')
        );
        self::assertFalse($roleResult);
        self::assertSame(401, $roleStatus);
        self::assertSame('Authentication required.', $roleResponse['message']);
    }

    public function testUserCanAccessUserRoleButNotAdminRole(): void
    {
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'role' => 'user',
        ];

        self::assertTrue($this->authMiddleware->handle());
        self::assertTrue($this->roleMiddleware->handle('user'));

        [$result, $response, $status] = $this->call(
            fn (): bool => $this->roleMiddleware->handle('admin')
        );
        self::assertFalse($result);
        self::assertSame(403, $status);
        self::assertSame(
            'You do not have permission to access this resource.',
            $response['message']
        );
    }

    public function testAdminCanAccessAdminRole(): void
    {
        $_SESSION['user'] = [
            'id' => 2,
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ];

        self::assertTrue($this->authMiddleware->handle());
        self::assertTrue($this->roleMiddleware->handle('admin'));
    }

    public function testMiddlewareRequiresAnActiveSession(): void
    {
        session_write_close();

        try {
            $this->authMiddleware->handle();
            self::fail('Expected AuthMiddleware to require an active session.');
        } catch (RuntimeException $exception) {
            self::assertSame('Session has not been started.', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->roleMiddleware->handle('user');
    }

    public function testRoleMiddlewareRejectsUnknownRequiredRoles(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->roleMiddleware->handle('owner');
    }

    private function call(callable $action): array
    {
        ob_start();
        $result = $action();
        $body = ob_get_clean();
        $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return [$result, $response, http_response_code()];
    }
}
