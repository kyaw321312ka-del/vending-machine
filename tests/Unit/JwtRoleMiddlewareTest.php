<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\JwtRoleMiddleware;
use PHPUnit\Framework\TestCase;

final class JwtRoleMiddlewareTest extends TestCase
{
    private JwtRoleMiddleware $middleware;

    protected function setUp(): void
    {
        unset($_SERVER['AUTH_USER']);
        http_response_code(200);
        $this->middleware = new JwtRoleMiddleware();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['AUTH_USER']);
    }

    public function testMalformedAuthenticatedUserIsRejected(): void
    {
        foreach ([
            ['id' => '12', 'role' => 'admin'],
            ['id' => 0, 'role' => 'user'],
            ['id' => 12, 'role' => 'owner'],
        ] as $user) {
            $_SERVER['AUTH_USER'] = $user;

            [$result, $response, $status] = $this->call();

            self::assertFalse($result);
            self::assertSame(401, $status);
            self::assertSame('Authentication required.', $response['message']);
        }
    }

    public function testValidUserIsAuthorizedForMatchingRole(): void
    {
        $_SERVER['AUTH_USER'] = ['id' => 12, 'role' => 'user'];

        self::assertTrue($this->middleware->handle('user'));
    }

    public function testValidUserIsForbiddenFromDifferentRole(): void
    {
        $_SERVER['AUTH_USER'] = ['id' => 12, 'role' => 'user'];

        [$result, $response, $status] = $this->call('admin');

        self::assertFalse($result);
        self::assertSame(403, $status);
        self::assertSame(
            'You do not have permission to access this resource.',
            $response['message']
        );
    }

    private function call(string $requiredRole = 'user'): array
    {
        ob_start();
        $result = $this->middleware->handle($requiredRole);
        $body = ob_get_clean();

        return [
            $result,
            json_decode($body, true, 512, JSON_THROW_ON_ERROR),
            http_response_code(),
        ];
    }
}
