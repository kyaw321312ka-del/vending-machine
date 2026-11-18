<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\ProductRepositoryInterface;
use App\Contracts\PurchaseServiceInterface;
use App\Contracts\TransactionRepositoryInterface;
use App\Controllers\ProductsController;
use App\Validation\ProductValidator;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ProductsControllerDependencyInjectionTest extends TestCase
{
    private ProductRepositoryInterface $products;
    private PurchaseServiceInterface $purchaseService;
    private TransactionRepositoryInterface $transactions;
    private ProductsController $controller;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        session_id('products-mock-'.bin2hex(random_bytes(8)));
        self::assertTrue(session_start());
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        http_response_code(200);

        $this->products = $this->createMock(ProductRepositoryInterface::class);
        $this->purchaseService = $this->createMock(PurchaseServiceInterface::class);
        $this->transactions = $this->createMock(TransactionRepositoryInterface::class);
        $this->controller = new ProductsController(
            $this->products,
            new ProductValidator(),
            $this->purchaseService,
            $this->transactions
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        unset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_X_CSRF_TOKEN']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function testIndexUsesMockedContractsWithoutOpeningDatabaseConnection(): void
    {
        $this->products->expects(self::once())
            ->method('countAll')
            ->willReturn(0);
        $this->products->expects(self::once())
            ->method('getAll')
            ->with(10, 0, 'id', 'ASC')
            ->willReturn([]);

        ob_start();
        $this->controller->index();
        $html = ob_get_clean();

        self::assertSame(200, http_response_code());
        self::assertStringContainsString('Page 1 of 1 (0 products)', $html);
    }

    public function testProductDetailsUsesMockedProductAndRendersEscapedDetails(): void
    {
        $this->authenticate();
        $_SESSION['_csrf_token'] = str_repeat('a', 64);
        $this->products->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($this->product('<Water>'));

        ob_start();
        $this->controller->show('42');
        $html = ob_get_clean();

        self::assertSame(200, http_response_code());
        self::assertStringContainsString('&lt;Water&gt;', $html);
        self::assertStringNotContainsString('<Water>', $html);
        self::assertStringContainsString('name="_csrf_token" value="'.str_repeat('a', 64).'"', $html);
    }

    public function testProductCreationUsesRepositoryAndRedirects(): void
    {
        $this->preparePost([
            'name' => '  Water  ',
            'price' => '1.250',
            'quantity_available' => '8',
        ]);
        $this->products->expects(self::once())
            ->method('create')
            ->with('Water', '1.250', 8)
            ->willReturn(51);

        $this->controller->store();

        self::assertSame(303, http_response_code());
        self::assertSame('Product created successfully.', $_SESSION['_flash_success']);
    }

    public function testProductUpdateUsesRouteIdAndRedirects(): void
    {
        $this->preparePost([
            'name' => 'Updated Water',
            'price' => '2.500',
            'quantity_available' => '3',
        ]);
        $this->products->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($this->product('Water'));
        $this->products->expects(self::once())
            ->method('update')
            ->with(42, 'Updated Water', '2.500', 3)
            ->willReturn(true);

        $this->controller->update('42');

        self::assertSame(303, http_response_code());
        self::assertSame('Product updated successfully.', $_SESSION['_flash_success']);
    }

    public function testProductDeletionUsesRepositoryAndRedirects(): void
    {
        $this->preparePost([]);
        $this->products->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($this->product('Water'));
        $this->products->expects(self::once())
            ->method('delete')
            ->with(42)
            ->willReturn(true);

        $this->controller->destroy('42');

        self::assertSame(303, http_response_code());
        self::assertSame('Product deleted successfully.', $_SESSION['_flash_success']);
    }

    public function testPurchaseSuccessCallsServiceAndSetsSuccessFlash(): void
    {
        $this->authenticate();
        $this->preparePost(['quantity' => '2']);
        $this->products->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($this->product('Water'));
        $this->purchaseService->expects(self::once())
            ->method('purchase')
            ->with(7, 42, 2)
            ->willReturn([
                'transaction_id' => 80,
                'user_id' => 7,
                'product_id' => 42,
                'product_name' => 'Water',
                'quantity' => 2,
                'unit_price' => '1.250',
                'total_price' => '2.500',
                'remaining_quantity' => 6,
            ]);

        $this->controller->purchase('42');

        self::assertSame(303, http_response_code());
        self::assertStringContainsString('2 × Water for 2.500', $_SESSION['_flash_success']);
    }

    public function testPurchaseFailureSetsFriendlyErrorFlash(): void
    {
        $this->authenticate();
        $this->preparePost(['quantity' => '2']);
        $this->products->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($this->product('Water'));
        $this->purchaseService->expects(self::once())
            ->method('purchase')
            ->with(7, 42, 2)
            ->willThrowException(new DomainException('Insufficient product inventory.'));

        $this->controller->purchase('42');

        self::assertSame(303, http_response_code());
        self::assertSame('There is not enough stock to complete this purchase.', $_SESSION['_flash_error']);
    }

    public function testTransactionHistoryUsesAuthenticatedUserId(): void
    {
        $this->authenticate();
        $this->transactions->expects(self::once())
            ->method('countByUserId')
            ->with(7)
            ->willReturn(1);
        $this->transactions->expects(self::once())
            ->method('getByUserId')
            ->with(7, 10, 0)
            ->willReturn([[
                'id' => 1,
                'user_id' => 7,
                'product_id' => 42,
                'quantity' => 2,
                'unit_price' => '1.250',
                'total_price' => '2.500',
                'created_at' => '2026-01-02 03:04:05',
                'product_name' => '<Water>',
            ]]);

        ob_start();
        $this->controller->history();
        $html = ob_get_clean();

        self::assertSame(200, http_response_code());
        self::assertStringContainsString('&lt;Water&gt;', $html);
        self::assertStringNotContainsString('<Water>', $html);
        self::assertStringContainsString('2.500', $html);
    }

    private function preparePost(array $post): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['_csrf_token'] = str_repeat('b', 64);
        $_POST = [...$post, '_csrf_token' => $_SESSION['_csrf_token']];
    }

    private function authenticate(): void
    {
        $_SESSION['user'] = [
            'id' => 7,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'user',
        ];
    }

    private function product(string $name): array
    {
        return [
            'id' => 42,
            'name' => $name,
            'price' => '1.250',
            'quantity_available' => 8,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];
    }
}
