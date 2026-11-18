<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminProductCreateViewTest extends TestCase
{
    public function testFormEscapesCsrfTokenOldValuesAndValidationMessages(): void
    {
        $csrfToken = '" onfocus="alert(1)';
        $assetUrl = '/assets/css/output.css';
        $old = [
            'name' => '<script>alert(1)</script>',
            'price' => '3.990',
            'quantity_available' => '5',
        ];
        $errors = [
            'name' => '<script>error</script>',
            'price' => 'Price is invalid.',
        ];

        ob_start();
        require dirname(__DIR__, 2) . '/resources/views/admin/products/create.php';
        $html = ob_get_clean();

        self::assertStringContainsString('name="_csrf_token" value="&quot; onfocus=&quot;alert(1)"', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;script&gt;error&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('name="price"', $html);
        self::assertStringContainsString('step="0.001"', $html);
        self::assertStringContainsString('name="quantity_available"', $html);
        self::assertStringContainsString('min="0"', $html);
        self::assertStringContainsString('action="/admin/products" method="post"', $html);
        self::assertStringContainsString('href="/products"', $html);
    }
}
