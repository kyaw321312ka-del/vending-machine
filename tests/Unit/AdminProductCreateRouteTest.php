<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\ProductsController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use App\Routing\Router;
use App\Services\PurchaseService;
use App\Validation\ProductValidator;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdminProductCreateRouteTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        session_id('admin-create-'.bin2hex(random_bytes(8)));
        self::assertTrue(session_start());
        $_SESSION = [];
        $_SERVER['SCRIPT_NAME'] = '/vending-machine/public/index.php';
        http_response_code(200);

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                price TEXT NOT NULL,
                quantity_available INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $products = new ProductRepository($pdo);
        $controller = new ProductsController(
            $products,
            new ProductValidator(),
            new PurchaseService($pdo, $products, new TransactionRepository($pdo))
        );

        $this->router = new Router(
            static fn (string $class): object => $controller,
            static fn (string $class): object => match ($class) {
                AuthMiddleware::class => new AuthMiddleware(),
                RoleMiddleware::class => new RoleMiddleware(),
                default => throw new \RuntimeException('Unexpected middleware.'),
            }
        );
        $this->router->registerController(ProductsController::class);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function testAdminCanOpenCreatePageWithSessionCsrfAndEmptyFormState(): void
    {
        $token = str_repeat('a', 64);
        $_SESSION['_csrf_token'] = $token;
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ];

        ob_start();
        $this->router->dispatch('GET', '/admin/products/create');
        $html = ob_get_clean();

        self::assertSame(200, http_response_code());
        self::assertSame($token, $_SESSION['_csrf_token']);
        self::assertStringContainsString('name="_csrf_token" value="'.$token.'"', $html);
        self::assertStringContainsString('name="name"', $html);
        self::assertStringContainsString('value=""', $html);
        self::assertStringContainsString('Create Product', $html);
    }

    public function testGuestReceivesUnauthorizedResponse(): void
    {
        [$response, $status] = $this->dispatchWithJsonResponse();

        self::assertSame(401, $status);
        self::assertSame('Authentication required.', $response['message']);
    }

    public function testAuthenticatedNonAdminReceivesForbiddenResponse(): void
    {
        $_SESSION['user'] = [
            'id' => 2,
            'name' => 'User',
            'email' => 'user@example.com',
            'role' => 'user',
        ];

        [$response, $status] = $this->dispatchWithJsonResponse();

        self::assertSame(403, $status);
        self::assertSame('You do not have permission to access this resource.', $response['message']);
    }

    private function dispatchWithJsonResponse(): array
    {
        ob_start();
        $this->router->dispatch('GET', '/admin/products/create');
        $body = ob_get_clean();

        return [json_decode($body, true, 512, JSON_THROW_ON_ERROR), http_response_code()];
    }
}
