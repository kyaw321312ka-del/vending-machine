<?php

declare(strict_types=1);

namespace App\Contracts;

interface PurchaseServiceInterface
{
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
    public function purchase(int $userId, int $productId, int $quantity): array;
}
