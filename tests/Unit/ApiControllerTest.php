<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AuthApiController;
use App\Controllers\Api\ProductsApiController;
use App\Http\JsonRequest;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\JwtRoleMiddleware;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Routing\Router;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\PurchaseService;
use App\Validation\ProductValidator;
use PDO;
use PHPUnit\Framework\TestCase;

final class ApiControllerTest extends TestCase
{
    private PDO $pdo;
    private ProductRepository $products;
    private UserRepository $users;
    private TransactionRepository $transactions;
    private JwtService $jwt;
    private Router $router;
    private string $body = '';
    private int $adminId;
    private int $userId;

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
                role TEXT NOT NULL DEFAULT 'user',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
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

        $this->products = new ProductRepository($this->pdo);
        $this->users = new UserRepository($this->pdo);
        $this->transactions = new TransactionRepository($this->pdo);
        $passwordHash = password_hash('correct-horse-battery', PASSWORD_DEFAULT);
        $this->adminId = $this->users->create('Admin', 'admin@example.com', $passwordHash, 'admin');
        $this->userId = $this->users->create('User', 'user@example.com', $passwordHash);
        $this->jwt = new JwtService('test-secret-with-at-least-thirty-two-bytes');
        $jsonRequest = new JsonRequest(fn (): string => $this->body);
        $auth = new AuthService($this->users);
        $validator = new ProductValidator();
        $purchase = new PurchaseService(
            $this->pdo,
            $this->products,
            $this->transactions
        );

        $this->router = new Router(
            function (string $class) use ($auth, $jsonRequest, $validator, $purchase): object {
                return match ($class) {
                    AuthApiController::class => new AuthApiController($auth, $this->jwt, $jsonRequest),
                    ProductsApiController::class => new ProductsApiController(
                        $this->products,
                        $validator,
                        $purchase,
                        $this->transactions,
                        $jsonRequest
                    ),
                    default => throw new \RuntimeException("Unknown controller: {$class}"),
                };
            },
            fn (string $class): object => match ($class) {
                JwtAuthMiddleware::class => new JwtAuthMiddleware($this->jwt),
                JwtRoleMiddleware::class => new JwtRoleMiddleware(),
                default => throw new \RuntimeException("Unknown middleware: {$class}"),
            }
        );
        $this->router->registerController(AuthApiController::class);
        $this->router->registerController(ProductsApiController::class);
        $_GET = [];
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['AUTH_USER']);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['AUTH_USER']);
    }

    public function testAdminCanCreateProductAndNonAdminCannot(): void
    {
        $this->body = json_encode([
            'name' => 'Water',
            'price' => '1.250',
            'quantity_available' => 8,
        ], JSON_THROW_ON_ERROR);

        [$guest, $guestStatus] = $this->dispatch('POST', '/api/products');
        self::assertSame(401, $guestStatus);
        self::assertFalse($guest['success']);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$this->jwt->createToken($this->userId, 'user');
        [, $userStatus] = $this->dispatch('POST', '/api/products');
        self::assertSame(403, $userStatus);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$this->jwt->createToken($this->adminId, 'admin');
        [$created, $adminStatus] = $this->dispatch('POST', '/api/products');
        self::assertSame(201, $adminStatus);
        self::assertSame('Water', $created['data']['name']);
        self::assertSame(8, $created['data']['quantity_available']);
    }

    public function testPurchaseAndHistoryUseTheAuthenticatedUsersTransactions(): void
    {
        $productId = $this->products->create('Cola', '2.500', 5);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$this->jwt->createToken($this->userId, 'user');
        $this->body = json_encode(['quantity' => 2], JSON_THROW_ON_ERROR);

        [$purchase, $status] = $this->dispatch('POST', '/api/products/'.$productId.'/purchase');
        self::assertSame(201, $status);
        self::assertSame('5.000', $purchase['data']['total_price']);
        self::assertSame(3, (int) $this->products->findById($productId)['quantity_available']);

        [$history, $historyStatus] = $this->dispatch('GET', '/api/transactions');
        self::assertSame(200, $historyStatus);
        self::assertCount(1, $history['data']);
        self::assertSame($this->userId, $history['data'][0]['user_id']);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$this->jwt->createToken($this->adminId, 'admin');
        [$otherHistory] = $this->dispatch('GET', '/api/transactions');
        self::assertSame([], $otherHistory['data']);
    }

    public function testLoginReturnsBearerTokenAndInvalidCredentialsAreRejected(): void
    {
        $this->body = json_encode([
            'email' => 'user@example.com',
            'password' => 'correct-horse-battery',
        ], JSON_THROW_ON_ERROR);

        [$response, $status] = $this->dispatch('POST', '/api/auth/login');
        self::assertSame(200, $status);
        self::assertSame('Bearer', $response['data']['token_type']);
        self::assertSame(
            ['id' => $this->userId, 'role' => 'user'],
            $this->jwt->validateToken($response['data']['access_token'])
        );

        $this->body = json_encode([
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ], JSON_THROW_ON_ERROR);
        [, $badCredentialsStatus] = $this->dispatch('POST', '/api/auth/login');
        self::assertSame(401, $badCredentialsStatus);
    }

    public function testProductListingSupportsPaginationAndSafeSorting(): void
    {
        $this->products->create('Water', '1.000', 1);
        $this->products->create('Soda', '2.000', 2);
        $_GET = ['sort_by' => 'name', 'sort_direction' => 'DESC', 'per_page' => '1'];

        [$response, $status] = $this->dispatch('GET', '/api/products');

        self::assertSame(200, $status);
        self::assertSame('Water', $response['data'][0]['name']);
        self::assertSame(2, $response['pagination']['total']);
        self::assertSame(2, $response['pagination']['total_pages']);

        $_GET['sort_by'] = 'password';
        [, $invalidSortStatus] = $this->dispatch('GET', '/api/products');
        self::assertSame(422, $invalidSortStatus);
    }

    public function testInvalidProductInputAndReferencedProductDeletionAreReported(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$this->jwt->createToken($this->adminId, 'admin');
        $this->body = json_encode([
            'name' => '',
            'price' => '0',
            'quantity_available' => -1,
        ], JSON_THROW_ON_ERROR);

        [$invalidProduct, $invalidStatus] = $this->dispatch('POST', '/api/products');
        self::assertSame(422, $invalidStatus);
        self::assertArrayHasKey('name', $invalidProduct['errors']);
        self::assertArrayHasKey('price', $invalidProduct['errors']);

        $productId = $this->products->create('Referenced product', '1.000', 1);
        $this->transactions->create($this->userId, $productId, 1, '1.000', '1.000');
        [$deletion, $deleteStatus] = $this->dispatch('DELETE', '/api/products/'.$productId);

        self::assertSame(409, $deleteStatus);
        self::assertStringContainsString('transaction history', $deletion['message']);
        self::assertNotNull($this->products->findById($productId));
    }

    /**
     * @return array{array<string, mixed>, int}
     */
    private function dispatch(string $method, string $path): array
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        ob_start();
        $this->router->dispatch($method, $path);
        $body = ob_get_clean();

        return [json_decode($body, true, 512, JSON_THROW_ON_ERROR), http_response_code()];
    }
}
