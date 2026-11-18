<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ProductRepositoryInterface;
use App\Contracts\TransactionRepositoryInterface;
use App\Contracts\PurchaseServiceInterface;
use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

final class PurchaseService implements PurchaseServiceInterface
{
    public function __construct(
        private PDO $pdo,
        private ProductRepositoryInterface $products,
        private TransactionRepositoryInterface $transactions
    ) {
    }

    /**
     * @return array{
     *     transaction_id: int,
     *     user_id: int,
     *     product_id: int,
     *     product_name: string,
     *     quantity: int,
     *     unit_price: string,
     *     total_price: string,
     *     remaining_quantity: int
     * }
     */
    public function purchase(int $userId, int $productId, int $quantity): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('A valid user ID is required.');
        }

        if ($productId <= 0) {
            throw new InvalidArgumentException('A valid product ID is required.');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Purchase quantity must be greater than zero.');
        }

        $this->pdo->beginTransaction();

        try {
            $product = $this->products->findByIdForUpdate($productId);

            if ($product === null) {
                throw new DomainException('Product not found.');
            }

            if ((int) ($product['is_enabled'] ?? 0) !== 1) {
                throw new DomainException('Product is not available for purchase.');
            }

            $availableQuantity = (int) $product['quantity_available'];
            if ($availableQuantity < $quantity) {
                throw new DomainException('Insufficient product inventory.');
            }

            $unitPrice = (string) $product['price'];
            $unitPriceMilli = $this->toMilliUnits($unitPrice);

            if ($unitPriceMilli <= 0) {
                throw new DomainException('Invalid product price.');
            }

            if ($unitPriceMilli > intdiv(PHP_INT_MAX, $quantity)) {
                throw new DomainException('Purchase total is too large.');
            }

            $totalPrice = $this->fromMilliUnits($unitPriceMilli * $quantity);
            $remainingQuantity = $availableQuantity - $quantity;

            if (!$this->products->updateQuantity($productId, $remainingQuantity)) {
                throw new DomainException('Unable to update product inventory.');
            }

            $transactionId = $this->transactions->create(
                $userId,
                $productId,
                $quantity,
                $unitPrice,
                $totalPrice
            );

            $this->pdo->commit();

            return [
                'transaction_id' => $transactionId,
                'user_id' => $userId,
                'product_id' => $productId,
                'product_name' => (string) $product['name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'remaining_quantity' => $remainingQuantity,
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function toMilliUnits(string $price): int
    {
        if (!preg_match('/^\d+(?:\.\d{1,3})?$/D', $price)) {
            throw new DomainException('Invalid decimal price.');
        }

        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $fraction = str_pad($fraction, 3, '0');

        return ((int) $whole * 1000) + (int) $fraction;
    }

    private function fromMilliUnits(int $amount): string
    {
        $whole = intdiv($amount, 1000);
        $fraction = $amount % 1000;

        return sprintf('%d.%03d', $whole, $fraction);
    }
}
