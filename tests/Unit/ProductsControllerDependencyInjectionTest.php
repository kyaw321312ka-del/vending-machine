<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\ProductRepositoryInterface;
use App\Contracts\PurchaseServiceInterface;
use App\Contracts\TransactionRepositoryInterface;
use App\Controllers\ProductsController;
use App\Validation\ProductValidator;
use PHPUnit\Framework\TestCase;

final class ProductsControllerDependencyInjectionTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        unset($_SERVER['SCRIPT_NAME']);
    }

    public function testIndexUsesMockedContractsWithoutOpeningDatabaseConnection(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::once())
            ->method('countAll')
            ->willReturn(0);
        $products->expects(self::once())
            ->method('getAll')
            ->with(10, 0, 'id', 'ASC')
            ->willReturn([]);

        $controller = new ProductsController(
            $products,
            new ProductValidator(),
            $this->createMock(PurchaseServiceInterface::class),
            $this->createMock(TransactionRepositoryInterface::class)
        );

        ob_start();
        $controller->index();
        $html = ob_get_clean();

        self::assertSame(200, http_response_code());
        self::assertStringContainsString('Page 1 of 1 (0 products)', $html);
    }
}
