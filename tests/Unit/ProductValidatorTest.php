<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validation\ProductValidator;
use PHPUnit\Framework\TestCase;

final class ProductValidatorTest extends TestCase
{
    private ProductValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ProductValidator();
    }

    public function testValidProductDataPassesIncludingDatabaseMaximums(): void
    {
        self::assertSame([], $this->validator->validate([
            'name' => 'Pepsi',
            'price' => '6.885',
            'quantity_available' => '50',
        ]));
        self::assertSame([], $this->validator->validate([
            'name' => str_repeat('P', 150),
            'price' => '9999999.999',
            'quantity_available' => '4294967295',
        ]));
    }

    public function testMissingOrInvalidProductNameIsRejected(): void
    {
        self::assertArrayHasKey('name', $this->validator->validate($this->validData(['name' => '  '])));
        self::assertArrayHasKey('name', $this->validator->validate($this->validData([
            'name' => str_repeat('P', 151),
        ])));
        self::assertArrayHasKey('name', $this->validator->validate($this->validData([
            'name' => ['not', 'text'],
        ])));
    }

    public function testMissingZeroNegativeAndMalformedPricesAreRejected(): void
    {
        foreach (['', '0', '0.000', '-1', '1.2345', '10000000', '99999999.999'] as $price) {
            self::assertArrayHasKey(
                'price',
                $this->validator->validate($this->validData(['price' => $price])),
                'Expected price to be rejected: '.$price
            );
        }

        self::assertArrayHasKey('price', $this->validator->validate($this->validData(['price' => null])));
    }

    public function testMissingNegativeNonIntegerAndOutOfRangeQuantitiesAreRejected(): void
    {
        foreach ([null, '', '-1', '1.5', '4294967296', ['1']] as $quantity) {
            self::assertArrayHasKey(
                'quantity_available',
                $this->validator->validate($this->validData(['quantity_available' => $quantity]))
            );
        }
    }

    public function testAvailabilityAcceptsExplicitEnabledAndDisabledValues(): void
    {
        foreach ([true, false, 1, 0, '1', '0'] as $enabled) {
            self::assertArrayNotHasKey(
                'is_enabled',
                $this->validator->validate($this->validData(['is_enabled' => $enabled]))
            );
        }

        self::assertArrayHasKey(
            'is_enabled',
            $this->validator->validate($this->validData(['is_enabled' => 'yes']))
        );
    }

    private function validData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Coke',
            'price' => '3.990',
            'quantity_available' => '50',
        ], $overrides);
    }
}
