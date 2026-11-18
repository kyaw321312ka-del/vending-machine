<?php

declare(strict_types=1);

namespace App\Validation;

final class ProductValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $errors = [];

        $name = $data['name'] ?? null;
        if (!is_string($name)) {
            $errors['name'] = 'Product name is required.';
        } else {
            $name = trim($name);
            if ($name === '') {
                $errors['name'] = 'Product name is required.';
            } elseif (mb_strlen($name) > 150) {
                $errors['name'] = 'Product name must not exceed 150 characters.';
            }
        }

        $price = $data['price'] ?? null;
        if (!is_string($price) && !is_int($price)) {
            $errors['price'] = 'Product price is required.';
        } else {
            $price = trim((string) $price);

            if ($price === '') {
                $errors['price'] = 'Product price is required.';
            } elseif (!preg_match('/^\d+(?:\.\d{1,3})?$/D', $price)) {
                $errors['price'] = 'Price must be a valid number with up to 3 decimal places.';
            } elseif (!$this->isPositiveAndWithinRange($price)) {
                $errors['price'] = 'Price must be positive and within the supported range.';
            }
        }

        $quantity = $data['quantity_available'] ?? null;
        if (!is_int($quantity) && !is_string($quantity)) {
            $errors['quantity_available'] = 'Quantity must be a valid integer.';
        } else {
            $quantity = is_string($quantity) ? trim($quantity) : $quantity;
            $validatedQuantity = filter_var($quantity, FILTER_VALIDATE_INT);

            if ($validatedQuantity === false) {
                $errors['quantity_available'] = 'Quantity must be a valid integer.';
            } elseif ($validatedQuantity < 0) {
                $errors['quantity_available'] = 'Quantity cannot be negative.';
            } elseif ($validatedQuantity > 4294967295) {
                $errors['quantity_available'] = 'Quantity exceeds the supported range.';
            }
        }

        if (
            array_key_exists('is_enabled', $data)
            && !in_array($data['is_enabled'], [true, false, 0, 1, '0', '1'], true)
        ) {
            $errors['is_enabled'] = 'Availability must be enabled or disabled.';
        }

        return $errors;
    }

    private function isPositiveAndWithinRange(string $price): bool
    {
        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        if (strlen($whole) > 7) {
            return false;
        }

        return $whole !== '0' || preg_match('/[1-9]/', $fraction) === 1;
    }
}
