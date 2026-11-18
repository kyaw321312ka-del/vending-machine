<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\AuthController;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    private UserRepository $users;
    private AuthController $controller;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        session_id('auth-controller-'.bin2hex(random_bytes(8)));
        self::assertTrue(session_start());

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
        $this->controller = new AuthController(new AuthService($this->users));
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function testMutatingEndpointsRequirePostAndCsrfToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        [$methodResponse, $methodStatus] = $this->call([$this->controller, 'register']);
        self::assertSame(405, $methodStatus);
        self::assertFalse($methodResponse['success']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        [$csrfResponse, $csrfStatus] = $this->call([$this->controller, 'register']);
        self::assertSame(403, $csrfStatus);
        self::assertFalse($csrfResponse['success']);
    }

    public function testRegistrationReturnsJsonAndCreatesUser(): void
    {
        $token = $this->csrfToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $_POST = [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password-123',
        ];

        [$response, $status] = $this->call([$this->controller, 'register']);

        self::assertSame(201, $status);
        self::assertTrue($response['success']);
        self::assertSame(1, $response['data']['user_id']);
        self::assertSame('user', $this->users->findByEmail('ada@example.com')['role']);
    }

    public function testLoginMeAndLogoutUseTheSession(): void
    {
        $this->users->create(
            'Grace Hopper',
            'grace@example.com',
            password_hash('password-123', PASSWORD_DEFAULT)
        );
        $token = $this->csrfToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $_POST = [
            'email' => 'grace@example.com',
            'password' => 'password-123',
        ];

        [$loginResponse, $loginStatus] = $this->call([$this->controller, 'login']);
        self::assertSame(200, $loginStatus);
        self::assertTrue($loginResponse['success']);
        self::assertArrayNotHasKey('password', $loginResponse['data']['user']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        [$meResponse, $meStatus] = $this->call([$this->controller, 'me']);
        self::assertSame(200, $meStatus);
        self::assertSame('Grace Hopper', $meResponse['data']['user']['name']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_csrf_token' => $token];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        [$logoutResponse, $logoutStatus] = $this->call([$this->controller, 'logout']);
        self::assertSame(200, $logoutStatus);
        self::assertTrue($logoutResponse['success']);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testLoginFailureAndUnauthenticatedMeReturnUnauthorized(): void
    {
        $token = $this->csrfToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $_POST = ['email' => 'unknown@example.com', 'password' => 'wrong'];
        [$loginResponse, $loginStatus] = $this->call([$this->controller, 'login']);
        self::assertSame(401, $loginStatus);
        self::assertFalse($loginResponse['success']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        [$meResponse, $meStatus] = $this->call([$this->controller, 'me']);
        self::assertSame(401, $meStatus);
        self::assertFalse($meResponse['success']);
    }

    private function csrfToken(): string
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        [$response, $status] = $this->call([$this->controller, 'csrfToken']);
        self::assertSame(200, $status);

        return $response['data']['csrf_token'];
    }

    private function call(callable $action): array
    {
        ob_start();
        $action();
        $body = ob_get_clean();
        $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return [$response, http_response_code()];
    }

}
