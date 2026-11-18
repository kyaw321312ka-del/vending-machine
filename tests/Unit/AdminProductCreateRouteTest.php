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
    private ProductRepository $products;

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
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

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
        $this->products = new ProductRepository($pdo);
        $controller = new ProductsController(
            $this->products,
            new ProductValidator(),
            new PurchaseService($pdo, $this->products, new TransactionRepository($pdo))
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

    public function testAdminCanOpenEditPageWithExistingValuesAndReusedCsrfToken(): void
    {
        $id = $this->products->create('<img src=x>', '6.885', 50);
        $token = str_repeat('f', 64);
        $_SESSION['_csrf_token'] = $token;
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ];

        ob_start();
        $this->router->dispatch('GET', '/admin/products/'.$id.'/edit');
        $html = ob_get_clean();

        self::assertSame(200, http_response_code());
        self::assertSame($token, $_SESSION['_csrf_token']);
        self::assertStringContainsString('name="_csrf_token" value="'.$token.'"', $html);
        self::assertStringContainsString('value="&lt;img src=x&gt;"', $html);
        self::assertStringContainsString('value="6.885"', $html);
        self::assertStringContainsString('value="50"', $html);
        self::assertStringContainsString('action="/vending-machine/public/admin/products/'.$id.'/update"', $html);
    }

    public function testInvalidOrMissingEditProductReturnsNotFound(): void
    {
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ];

        foreach (['not-an-id', '999'] as $id) {
            http_response_code(200);
            ob_start();
            $this->router->dispatch('GET', '/admin/products/'.$id.'/edit');
            $body = ob_get_clean();

            self::assertSame(404, http_response_code());
            self::assertSame('Product not found.', $body);
        }
    }

    public function testAdminCreatesProductAndSetsSuccessFlashForRedirect(): void
    {
        $token = str_repeat('b', 64);
        $_SESSION['_csrf_token'] = $token;
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            '_csrf_token' => $token,
            'name' => '  Pepsi  ',
            'price' => '6.885',
            'quantity_available' => '50',
        ];

        $this->dispatchWithOutput();

        self::assertSame(303, http_response_code());
        self::assertSame('Product created successfully.', $_SESSION['_flash_success']);
        self::assertSame('Pepsi', $this->products->getAll()[0]['name']);
        self::assertSame('6.885', $this->products->getAll()[0]['price']);
        self::assertSame(50, (int) $this->products->getAll()[0]['quantity_available']);
    }

    public function testInvalidProductIsRedisplayedWithOldValuesAndErrors(): void
    {
        $token = str_repeat('c', 64);
        $_SESSION['_csrf_token'] = $token;
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            '_csrf_token' => $token,
            'name' => '<script>alert(1)</script>',
            'price' => '0',
            'quantity_available' => '-1',
        ];

        $html = $this->dispatchWithOutput();

        self::assertSame(422, http_response_code());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('Price must be positive', $html);
        self::assertStringContainsString('Quantity cannot be negative.', $html);
        self::assertSame(0, $this->products->countAll());
    }

    public function testInvalidCsrfTokenIsRejectedWithoutCreatingProduct(): void
    {
        $_SESSION['_csrf_token'] = str_repeat('d', 64);
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            '_csrf_token' => str_repeat('e', 64),
            'name' => 'Coke',
            'price' => '3.990',
            'quantity_available' => '10',
        ];

        $body = $this->dispatchWithOutput();

        self::assertSame(403, http_response_code());
        self::assertSame('Invalid CSRF token.', $body);
        self::assertSame(0, $this->products->countAll());
    }

    public function testGuestAndRegularUserCannotCreateProduct(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        [$guest, $guestStatus] = $this->dispatchWithJsonResponse('POST', '/admin/products');
        self::assertSame(401, $guestStatus);
        self::assertSame('Authentication required.', $guest['message']);

        $_SESSION['user'] = [
            'id' => 2,
            'name' => 'User',
            'email' => 'user@example.com',
            'role' => 'user',
        ];
        [$user, $userStatus] = $this->dispatchWithJsonResponse('POST', '/admin/products');
        self::assertSame(403, $userStatus);
        self::assertSame('You do not have permission to access this resource.', $user['message']);
        self::assertSame(0, $this->products->countAll());
    }

    private function dispatchWithJsonResponse(string $method = 'GET', string $uri = '/admin/products/create'): array
    {
        ob_start();
        $this->router->dispatch($method, $uri);
        $body = ob_get_clean();

        return [json_decode($body, true, 512, JSON_THROW_ON_ERROR), http_response_code()];
    }

    private function dispatchWithOutput(): string
    {
        ob_start();
        $this->router->dispatch('POST', '/admin/products');

        return ob_get_clean();
    }
}
