<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\WebAuthController;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use PDO;
use PHPUnit\Framework\TestCase;

final class WebAuthControllerTest extends TestCase
{
    private UserRepository $users;
    private WebAuthController $controller;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        session_id('web-auth-'.bin2hex(random_bytes(8)));
        self::assertTrue(session_start());
        $_SESSION = [];
        $_POST = [];
        $_SERVER['SCRIPT_NAME'] = '/vending-machine/public/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'user',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->users = new UserRepository($pdo);
        $this->controller = new WebAuthController(new AuthService($this->users));
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        unset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_METHOD']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function testLoginAndRegistrationPagesUseSharedLayoutAndCsrfToken(): void
    {
        ob_start();
        $this->controller->showLogin();
        $loginHtml = ob_get_clean();

        self::assertSame(200, http_response_code());
        self::assertStringContainsString('Vending Machine', $loginHtml);
        self::assertStringContainsString('name="_csrf_token"', $loginHtml);
        self::assertStringContainsString('/vending-machine/public/auth/register', $loginHtml);

        ob_start();
        $this->controller->showRegister();
        $registerHtml = ob_get_clean();

        self::assertStringContainsString('Create account', $registerHtml);
        self::assertStringContainsString('minlength="8"', $registerHtml);
        self::assertStringContainsString('name="_csrf_token"', $registerHtml);
    }

    public function testRegistrationCreatesUserAndRedirectsToSignIn(): void
    {
        $this->post([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secure-pass-123',
        ]);

        $this->controller->register();

        self::assertSame(303, http_response_code());
        self::assertSame('Registration successful. Please sign in.', $_SESSION['_flash_success']);
        self::assertSame('user', $this->users->findByEmail('ada@example.com')['role']);
    }

    public function testLoginAuthenticatesAndRedirectsToProducts(): void
    {
        $this->users->create(
            'Grace Hopper',
            'grace@example.com',
            password_hash('secure-pass-123', PASSWORD_DEFAULT)
        );
        $this->post([
            'email' => 'grace@example.com',
            'password' => 'secure-pass-123',
        ]);

        $this->controller->login();

        self::assertSame(303, http_response_code());
        self::assertSame('Grace Hopper', $_SESSION['user']['name']);
        self::assertSame('Login successful.', $_SESSION['_flash_success']);
    }

    public function testInvalidLoginPreservesEmailAndRendersFriendlyError(): void
    {
        $this->post([
            'email' => 'unknown@example.com',
            'password' => 'wrong-password',
        ]);

        ob_start();
        $this->controller->login();
        $html = ob_get_clean();

        self::assertSame(422, http_response_code());
        self::assertStringContainsString('Invalid email or password.', $html);
        self::assertStringContainsString('value="unknown@example.com"', $html);
    }

    public function testRegistrationRejectsInvalidCsrfToken(): void
    {
        $_SESSION['_csrf_token'] = str_repeat('a', 64);
        $_POST = [
            '_csrf_token' => str_repeat('b', 64),
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secure-pass-123',
        ];

        ob_start();
        $this->controller->register();
        $html = ob_get_clean();

        self::assertSame(403, http_response_code());
        self::assertStringContainsString('Your session expired. Please try again.', $html);
        self::assertNull($this->users->findByEmail('ada@example.com'));
    }

    public function testAuthenticatedNavigationShowsCsrfProtectedLogoutForm(): void
    {
        $_SESSION['user'] = [
            'id' => 3,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ];
        $_SESSION['_csrf_token'] = str_repeat('d', 64);

        ob_start();
        $this->controller->showLogin();
        $html = ob_get_clean();

        self::assertStringContainsString('action="/vending-machine/public/auth/logout"', $html);
        self::assertStringContainsString('name="_csrf_token" value="'.str_repeat('d', 64).'"', $html);
        self::assertStringContainsString('>Log out</button>', $html);
    }

    public function testLogoutRequiresCsrfAndDestroysSession(): void
    {
        $_SESSION['user'] = [
            'id' => 3,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ];
        $_SESSION['_csrf_token'] = str_repeat('e', 64);
        $_POST['_csrf_token'] = str_repeat('f', 64);

        ob_start();
        $this->controller->logout();
        $invalidCsrfBody = ob_get_clean();

        self::assertSame(403, http_response_code());
        self::assertSame('Invalid CSRF token.', $invalidCsrfBody);
        self::assertArrayHasKey('user', $_SESSION);

        $_POST['_csrf_token'] = $_SESSION['_csrf_token'];
        $this->controller->logout();

        self::assertSame(303, http_response_code());
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    private function post(array $fields): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['_csrf_token'] = str_repeat('c', 64);
        $_POST = [
            ...$fields,
            '_csrf_token' => $_SESSION['_csrf_token'],
        ];
    }
}
