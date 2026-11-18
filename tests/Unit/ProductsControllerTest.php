<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\ProductsController;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use App\Services\PurchaseService;
use App\Validation\ProductValidator;
use PDO;
use PHPUnit\Framework\TestCase;

final class ProductsControllerTest extends TestCase
{
    private PDO $pdo;
    private ProductRepository $products;
    private ProductsController $controller;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        $this->products = new ProductRepository($this->pdo);
        $purchaseService = new PurchaseService(
            $this->pdo,
            $this->products,
            new TransactionRepository($this->pdo)
        );
        $this->controller = new ProductsController(
            $this->products,
            new ProductValidator(),
            $purchaseService,
            new TransactionRepository($this->pdo)
        );

        $_GET = [];
        $_SERVER['SCRIPT_NAME'] = '/vending-machine/public/index.php';
        http_response_code(200);
    }

    public function testIndexRendersSortedPaginatedProductsAndEscapesHtml(): void
    {
        $this->products->create('<script>alert(1)</script>', '0.500', 4);
        $this->products->create('Water', '0.500', 10);
        $_GET = [
            'page' => '1',
            'per_page' => '1',
            'sort_by' => 'name',
            'sort_direction' => 'ASC',
        ];

        ob_start();
        $this->controller->index();
        $html = ob_get_clean();

        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('Page 1 of 2 (2 products)', $html);
        self::assertStringContainsString('/vending-machine/public/assets/css/output.css', $html);
    }

    public function testIndexRejectsInvalidPaginationOrSortParameters(): void
    {
        $_GET = ['page' => 'not-a-page'];

        ob_start();
        $this->controller->index();
        $body = ob_get_clean();

        self::assertSame(400, http_response_code());
        self::assertSame('Invalid product listing parameters.', $body);
    }

    public function testIndexDisplaysAndConsumesFriendlyErrorFlash(): void
    {
        session_id('products-flash-'.bin2hex(random_bytes(8)));
        self::assertTrue(session_start());
        $_SESSION['_flash_error'] = 'This product cannot be deleted because it has transaction history.';

        ob_start();
        $this->controller->index();
        $html = ob_get_clean();

        self::assertStringContainsString(
            'This product cannot be deleted because it has transaction history.',
            $html
        );
        self::assertArrayNotHasKey('_flash_error', $_SESSION);
        $_SESSION = [];
        session_destroy();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

}
