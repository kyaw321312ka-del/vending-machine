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

final class PurchaseControllerFlowTest extends TestCase
{
    private PDO $pdo;
    private ProductRepository $products;
    private TransactionRepository $transactions;
    private Router $router;
    private int $userId;
    private int $otherUserId;
    private int $adminId;
    private int $productId;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        session_id('purchase-flow-'.bin2hex(random_bytes(8)));
        self::assertTrue(session_start());
        $_SESSION = [];
        $_SERVER['SCRIPT_NAME'] = '/vending-machine/public/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        http_response_code(200);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec(
            "CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'user'
            )"
        );
        $this->pdo->exec(
            'CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                price TEXT NOT NULL,
                quantity_available INTEGER NOT NULL DEFAULT 0,
                is_enabled INTEGER NOT NULL DEFAULT 0,
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

        $this->userId = $this->createUser('Customer', 'customer@example.com');
        $this->otherUserId = $this->createUser('Other Customer', 'other@example.com');
        $this->adminId = $this->createUser('Administrator', 'admin@example.com', 'admin');
        $this->products = new ProductRepository($this->pdo);
        $this->transactions = new TransactionRepository($this->pdo);
        $this->productId = $this->products->create('<Coke>', '6.885', 5, true);
        $purchaseService = new PurchaseService($this->pdo, $this->products, $this->transactions);
        $controller = new ProductsController(
            $this->products,
            new ProductValidator(),
            $purchaseService,
            $this->transactions
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
        $_POST = [];
        $_GET = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function testProductDetailsAreEscapedAndPurchaseFormRequiresLogin(): void
    {
        $html = $this->dispatchWithOutput('GET', '/products/'.$this->productId);

        self::assertSame(200, http_response_code());
        self::assertStringContainsString('&lt;Coke&gt;', $html);
        self::assertStringNotContainsString('<Coke>', $html);
        self::assertStringContainsString('Please sign in to your account before purchasing this product.', $html);
        self::assertStringNotContainsString('name="_csrf_token"', $html);
    }

    public function testProductListingIsAvailableAtApplicationRoot(): void
    {
        $html = $this->dispatchWithOutput('GET', '/');

        self::assertSame(200, http_response_code());
        self::assertStringContainsString('&lt;Coke&gt;', $html);
        self::assertStringContainsString('Products', $html);
    }

    public function testCustomersCannotSeeDisabledOrOutOfStockProducts(): void
    {
        $disabled = $this->products->create('Disabled', '1.000', 5);
        $empty = $this->products->create('Empty', '1.000', 0, true);

        $listing = $this->dispatchWithOutput('GET', '/products');
        self::assertStringContainsString('&lt;Coke&gt;', $listing);
        self::assertStringNotContainsString('Disabled', $listing);
        self::assertStringNotContainsString('Empty', $listing);

        $disabledDetails = $this->dispatchWithOutput('GET', '/products/'.$disabled);
        self::assertSame(404, http_response_code());
        self::assertSame('Product not found.', $disabledDetails);

        $emptyDetails = $this->dispatchWithOutput('GET', '/products/'.$empty);
        self::assertSame(404, http_response_code());
        self::assertSame('Product not found.', $emptyDetails);
    }

    public function testAdminProductListingOffersCreateEditAndCsrfProtectedDelete(): void
    {
        $this->authenticate($this->adminId, 'admin');
        $this->products->create('Disabled', '1.000', 5);
        $this->products->create('Empty', '1.000', 0, true);
        $token = str_repeat('a', 64);
        $_SESSION['_csrf_token'] = $token;

        $html = $this->dispatchWithOutput('GET', '/products');

        self::assertSame(200, http_response_code());
        self::assertStringContainsString('/admin/products/create', $html);
        self::assertStringContainsString('/admin/products/'.$this->productId.'/edit', $html);
        self::assertStringContainsString(
            'action="/vending-machine/public/admin/products/'.$this->productId.'/delete"',
            $html
        );
        self::assertStringContainsString('Disabled', $html);
        self::assertStringContainsString('Empty', $html);
        self::assertStringContainsString('name="_csrf_token" value="'.$token.'"', $html);
    }

    public function testAuthenticatedProductDetailsHavePurchaseFormAndSharedCsrfToken(): void
    {
        $this->authenticate($this->userId);
        $token = str_repeat('a', 64);
        $_SESSION['_csrf_token'] = $token;

        $html = $this->dispatchWithOutput('GET', '/products/'.$this->productId);

        self::assertStringContainsString('name="_csrf_token" value="'.$token.'"', $html);
        self::assertStringContainsString('action="/vending-machine/public/products/'.$this->productId.'/purchase"', $html);
        self::assertStringContainsString('View Purchase History', $html);
    }

    public function testAdministratorProductDetailsDoNotOfferPurchaseForm(): void
    {
        $this->authenticate($this->adminId, 'admin');

        $html = $this->dispatchWithOutput('GET', '/products/'.$this->productId);

        self::assertSame(200, http_response_code());
        self::assertStringContainsString('Administrator Account', $html);
        self::assertStringContainsString('View All Transactions', $html);
        self::assertStringNotContainsString('/products/'.$this->productId.'/purchase', $html);
    }

    public function testAdministratorCannotPurchaseProduct(): void
    {
        $this->authenticate($this->adminId, 'admin');
        $_SESSION['_csrf_token'] = str_repeat('a', 64);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            '_csrf_token' => $_SESSION['_csrf_token'],
            'quantity' => '1',
        ];

        $body = $this->dispatchWithOutput('POST', '/products/'.$this->productId.'/purchase');

        self::assertSame(403, http_response_code());
        self::assertSame('Only customer accounts can purchase products.', $body);
        self::assertSame(5, (int) $this->products->findById($this->productId)['quantity_available']);
        self::assertSame(0, $this->transactions->countAll());
    }

    public function testPurchaseDeductsStockRecordsTransactionAndRedirects(): void
    {
        $token = str_repeat('b', 64);
        $this->preparePurchase($token, '2');

        $this->dispatchWithOutput('POST', '/products/'.$this->productId.'/purchase');

        self::assertSame(303, http_response_code());
        self::assertSame(3, (int) $this->products->findById($this->productId)['quantity_available']);
        self::assertSame(1, $this->transactions->countByUserId($this->userId));
        $history = $this->transactions->getByUserId($this->userId);
        self::assertSame('6.885', $history[0]['unit_price']);
        self::assertSame('13.770', $history[0]['total_price']);
        self::assertStringContainsString('13.770', $_SESSION['_flash_success']);
    }

    public function testInvalidQuantityIsRedisplayedAndDoesNotPurchase(): void
    {
        $this->preparePurchase(str_repeat('c', 64), '0');

        $html = $this->dispatchWithOutput('POST', '/products/'.$this->productId.'/purchase');

        self::assertSame(422, http_response_code());
        self::assertStringContainsString('Quantity must be a positive whole number.', $html);
        self::assertStringContainsString('value="0"', $html);
        self::assertSame(5, (int) $this->products->findById($this->productId)['quantity_available']);
        self::assertSame(0, $this->transactions->countAll());
    }

    public function testPurchaseRejectsMissingCsrfAndGuests(): void
    {
        $this->authenticate($this->userId);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['quantity' => '1'];
        $body = $this->dispatchWithOutput('POST', '/products/'.$this->productId.'/purchase');

        self::assertSame(403, http_response_code());
        self::assertSame('Invalid CSRF token.', $body);
        self::assertSame(5, (int) $this->products->findById($this->productId)['quantity_available']);

        $_SESSION = [];
        [$guest, $status] = $this->dispatchWithJsonResponse(
            'POST',
            '/products/'.$this->productId.'/purchase'
        );
        self::assertSame(401, $status);
        self::assertSame('Authentication required.', $guest['message']);
    }

    public function testInsufficientStockShowsFriendlyErrorWithoutChangingStock(): void
    {
        $this->preparePurchase(str_repeat('d', 64), '6');

        $this->dispatchWithOutput('POST', '/products/'.$this->productId.'/purchase');

        self::assertSame(303, http_response_code());
        self::assertSame(5, (int) $this->products->findById($this->productId)['quantity_available']);
        self::assertSame(0, $this->transactions->countAll());
        self::assertSame('There is not enough stock to complete this purchase.', $_SESSION['_flash_error']);
    }

    public function testTransactionHistoryOnlyShowsTheAuthenticatedUsersPurchases(): void
    {
        $this->transactions->create($this->userId, $this->productId, 1, '6.885', '6.885');
        $this->transactions->create($this->otherUserId, $this->productId, 2, '6.885', '13.770');
        $this->authenticate($this->userId);

        $html = $this->dispatchWithOutput('GET', '/transactions');

        self::assertSame(200, http_response_code());
        self::assertStringContainsString('Purchase History', $html);
        self::assertStringContainsString('6.885', $html);
        self::assertStringNotContainsString('13.770', $html);
        self::assertStringContainsString('1 purchases', $html);
    }

    public function testAdministratorCanViewAllTransactionRecords(): void
    {
        $this->transactions->create($this->userId, $this->productId, 1, '6.885', '6.885');
        $this->transactions->create($this->otherUserId, $this->productId, 2, '6.885', '13.770');
        $this->authenticate($this->adminId, 'admin');

        $html = $this->dispatchWithOutput('GET', '/transactions');

        self::assertSame(200, http_response_code());
        self::assertStringContainsString('All Transactions', $html);
        self::assertStringContainsString('Customer', $html);
        self::assertStringContainsString('Other Customer', $html);
        self::assertStringContainsString('2 transactions', $html);
        self::assertStringContainsString('13.770', $html);
    }

    public function testMissingProductDetailsReturn404(): void
    {
        $body = $this->dispatchWithOutput('GET', '/products/999');

        self::assertSame(404, http_response_code());
        self::assertSame('Product not found.', $body);
    }

    private function authenticate(int $userId, string $role = 'user'): void
    {
        $_SESSION['user'] = [
            'id' => $userId,
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'role' => $role,
        ];
    }

    private function preparePurchase(string $token, string $quantity): void
    {
        $this->authenticate($this->userId);
        $_SESSION['_csrf_token'] = $token;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            '_csrf_token' => $token,
            'quantity' => $quantity,
        ];
    }

    private function dispatchWithOutput(string $method, string $uri): string
    {
        http_response_code(200);
        ob_start();
        $this->router->dispatch($method, $uri);

        return ob_get_clean();
    }

    private function dispatchWithJsonResponse(string $method, string $uri): array
    {
        $body = $this->dispatchWithOutput($method, $uri);

        return [json_decode($body, true, 512, JSON_THROW_ON_ERROR), http_response_code()];
    }

    private function createUser(string $name, string $email, string $role = 'user'): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)'
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => password_hash('temporary', PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
