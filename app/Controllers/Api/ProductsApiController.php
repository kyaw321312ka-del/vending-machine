<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Contracts\ProductRepositoryInterface;
use App\Contracts\PurchaseServiceInterface;
use App\Contracts\TransactionRepositoryInterface;
use App\Http\JsonInputException;
use App\Http\JsonRequest;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\JwtRoleMiddleware;
use App\Routing\Route;
use App\Validation\ProductValidator;
use DomainException;
use InvalidArgumentException;
use PDOException;

final class ProductsApiController
{
    private JsonRequest $jsonRequest;

    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductValidator $validator,
        private PurchaseServiceInterface $purchases,
        private TransactionRepositoryInterface $transactions,
        ?JsonRequest $jsonRequest = null
    ) {
        $this->jsonRequest = $jsonRequest ?? new JsonRequest();
    }

    #[Route('GET', '/api/products')]
    public function index(): void
    {
        $pagination = $this->pagination();
        if ($pagination === null) {
            return;
        }

        $sortBy = $_GET['sort_by'] ?? 'id';
        $sortDirection = $_GET['sort_direction'] ?? 'ASC';
        $sortColumns = ['id', 'name', 'price', 'quantity_available', 'created_at', 'updated_at'];
        if (
            !is_string($sortBy)
            || !in_array($sortBy, $sortColumns, true)
            || !is_string($sortDirection)
            || !in_array(strtoupper($sortDirection), ['ASC', 'DESC'], true)
        ) {
            $this->respond(422, [
                'success' => false,
                'message' => 'Invalid product sorting options.',
            ]);

            return;
        }

        try {
            $items = array_map(
                $this->normalizeProduct(...),
                $this->products->getAll(
                    $pagination['per_page'],
                    $pagination['offset'],
                    $sortBy,
                    strtoupper($sortDirection)
                )
            );
            $total = $this->products->countAll();
        } catch (PDOException $exception) {
            $this->databaseError($exception);

            return;
        }

        $this->respond(200, [
            'success' => true,
            'data' => $items,
            'pagination' => $this->paginationMeta($pagination, $total),
        ]);
    }

    #[Route('GET', '/api/products/{id}')]
    public function show(string $id): void
    {
        $productId = $this->parseId($id);
        if ($productId === null) {
            $this->notFound('Product not found.');

            return;
        }

        try {
            $product = $this->products->findById($productId);
        } catch (PDOException $exception) {
            $this->databaseError($exception);

            return;
        }
        if ($product === null) {
            $this->notFound('Product not found.');

            return;
        }

        $this->respond(200, [
            'success' => true,
            'data' => $this->normalizeProduct($product),
        ]);
    }

    #[Route(
        'POST',
        '/api/products',
        [[JwtAuthMiddleware::class], [JwtRoleMiddleware::class, 'admin']]
    )]
    public function store(): void
    {
        $input = $this->readJsonBody();
        if ($input === null) {
            return;
        }

        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            $this->respond(422, [
                'success' => false,
                'message' => 'Product validation failed.',
                'errors' => $errors,
            ]);

            return;
        }

        $quantity = filter_var($input['quantity_available'], FILTER_VALIDATE_INT);
        if ($quantity === false) {
            $this->respond(422, [
                'success' => false,
                'message' => 'Product validation failed.',
                'errors' => ['quantity_available' => 'Quantity must be a valid integer.'],
            ]);

            return;
        }

        try {
            $id = $this->products->create(
                trim($input['name']),
                trim((string) $input['price']),
                $quantity
            );
            $product = $this->products->findById($id);
        } catch (PDOException $exception) {
            $this->databaseError($exception);

            return;
        }

        $this->respond(201, [
            'success' => true,
            'data' => $product === null ? ['id' => $id] : $this->normalizeProduct($product),
        ]);
    }

    #[Route(
        'PUT',
        '/api/products/{id}',
        [[JwtAuthMiddleware::class], [JwtRoleMiddleware::class, 'admin']]
    )]
    public function update(string $id): void
    {
        $productId = $this->parseId($id);
        if ($productId === null) {
            $this->notFound('Product not found.');

            return;
        }

        $input = $this->readJsonBody();
        if ($input === null) {
            return;
        }

        try {
            $existing = $this->products->findById($productId);
        } catch (PDOException $exception) {
            $this->databaseError($exception);

            return;
        }
        if ($existing === null) {
            $this->notFound('Product not found.');

            return;
        }

        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            $this->respond(422, [
                'success' => false,
                'message' => 'Product validation failed.',
                'errors' => $errors,
            ]);

            return;
        }

        $quantity = filter_var($input['quantity_available'], FILTER_VALIDATE_INT);
        if ($quantity === false) {
            $this->respond(422, [
                'success' => false,
                'message' => 'Product validation failed.',
                'errors' => ['quantity_available' => 'Quantity must be a valid integer.'],
            ]);

            return;
        }

        try {
            $updated = $this->products->update(
                $productId,
                trim($input['name']),
                trim((string) $input['price']),
                $quantity
            );
        } catch (PDOException $exception) {
            $this->databaseError($exception);

            return;
        }

        if (!$updated) {
            $this->notFound('Product not found.');

            return;
        }

        try {
            $updatedProduct = $this->products->findById($productId);
        } catch (PDOException $exception) {
            $this->databaseError($exception);

            return;
        }

        $this->respond(200, [
            'success' => true,
            'data' => $this->normalizeProduct($updatedProduct ?? $existing),
        ]);
    }

    #[Route(
        'DELETE',
        '/api/products/{id}',
        [[JwtAuthMiddleware::class], [JwtRoleMiddleware::class, 'admin']]
    )]
    public function delete(string $id): void
    {
        $productId = $this->parseId($id);
        if ($productId === null) {
            $this->notFound('Product not found.');

            return;
        }

        try {
            if ($this->products->findById($productId) === null) {
                $this->notFound('Product not found.');

                return;
            }

            $deleted = $this->products->delete($productId);
        } catch (PDOException $exception) {
            if ($this->isForeignKeyViolation($exception)) {
                $this->respond(409, [
                    'success' => false,
                    'message' => 'This product cannot be deleted because it has transaction history.',
                ]);

                return;
            }

            $this->databaseError($exception);

            return;
        }

        if (!$deleted) {
            $this->notFound('Product not found.');

            return;
        }

        $this->respond(200, [
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    #[Route(
        'POST',
        '/api/products/{id}/purchase',
        [[JwtAuthMiddleware::class], [JwtRoleMiddleware::class, 'user']]
    )]
    public function purchase(string $id): void
    {
        $productId = $this->parseId($id);
        if ($productId === null) {
            $this->notFound('Product not found.');

            return;
        }

        $input = $this->readJsonBody();
        if ($input === null) {
            return;
        }

        $quantity = $input['quantity'] ?? null;
        if (
            (!is_int($quantity) && !is_string($quantity))
            || filter_var($quantity, FILTER_VALIDATE_INT) === false
            || (int) $quantity <= 0
        ) {
            $this->respond(422, [
                'success' => false,
                'message' => 'Quantity must be a positive integer.',
            ]);

            return;
        }

        $user = $_SERVER['AUTH_USER'] ?? null;
        if (!is_array($user) || !isset($user['id']) || !is_int($user['id'])) {
            $this->respond(401, [
                'success' => false,
                'message' => 'Authentication required.',
            ]);

            return;
        }

        try {
            $result = $this->purchases->purchase($user['id'], $productId, (int) $quantity);
        } catch (DomainException $exception) {
            if ($exception->getMessage() === 'Product not found.') {
                $this->notFound($exception->getMessage());

                return;
            }

            if ($exception->getMessage() === 'Insufficient product inventory.') {
                $this->respond(409, [
                    'success' => false,
                    'message' => $exception->getMessage(),
                ]);

                return;
            }

            error_log($exception->getMessage());
            $this->respond(500, [
                'success' => false,
                'message' => 'Unable to complete the purchase.',
            ]);

            return;
        } catch (InvalidArgumentException $exception) {
            $this->respond(422, [
                'success' => false,
                'message' => $exception->getMessage(),
            ]);

            return;
        } catch (PDOException $exception) {
            $this->databaseError($exception);

            return;
        }

        $this->respond(201, [
            'success' => true,
            'data' => $result,
        ]);
    }

    #[Route(
        'GET',
        '/api/transactions',
        [[JwtAuthMiddleware::class]]
    )]
    public function transactionHistory(): void
    {
        $user = $_SERVER['AUTH_USER'] ?? null;
        if (!is_array($user) || !isset($user['id']) || !is_int($user['id'])) {
            $this->respond(401, [
                'success' => false,
                'message' => 'Authentication required.',
            ]);

            return;
        }

        $pagination = $this->pagination();
        if ($pagination === null) {
            return;
        }

        try {
            $transactionRows = ($user['role'] ?? null) === 'admin'
                ? $this->transactions->getAll(
                    $pagination['per_page'],
                    $pagination['offset']
                )
                : $this->transactions->getByUserId(
                    $user['id'],
                    $pagination['per_page'],
                    $pagination['offset']
                );
            $total = ($user['role'] ?? null) === 'admin'
                ? $this->transactions->countAll()
                : $this->transactions->countByUserId($user['id']);
            $transactions = array_map(
                static fn (array $transaction): array => [
                    ...$transaction,
                    'id' => (int) $transaction['id'],
                    'user_id' => (int) $transaction['user_id'],
                    'product_id' => (int) $transaction['product_id'],
                    'quantity' => (int) $transaction['quantity'],
                ],
                $transactionRows
            );
        } catch (PDOException $exception) {
            $this->databaseError($exception);

            return;
        }

        $this->respond(200, [
            'success' => true,
            'data' => $transactions,
            'pagination' => $this->paginationMeta($pagination, $total),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJsonBody(): ?array
    {
        try {
            return $this->jsonRequest->body();
        } catch (JsonInputException $exception) {
            $this->respond($exception->statusCode, [
                'success' => false,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{page: int, per_page: int, offset: int}|null
     */
    private function pagination(): ?array
    {
        $pageValue = $_GET['page'] ?? '1';
        $perPageValue = $_GET['per_page'] ?? '10';
        $page = is_string($pageValue) || is_int($pageValue)
            ? filter_var($pageValue, FILTER_VALIDATE_INT)
            : false;
        $perPage = is_string($perPageValue) || is_int($perPageValue)
            ? filter_var($perPageValue, FILTER_VALIDATE_INT)
            : false;

        if (
            $page === false
            || $page < 1
            || $perPage === false
            || $perPage < 1
            || $perPage > 100
            || $page - 1 > intdiv(PHP_INT_MAX, $perPage)
        ) {
            $this->respond(422, [
                'success' => false,
                'message' => 'Page must be positive and per_page must be between 1 and 100.',
            ]);

            return null;
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    /**
     * @param array{page: int, per_page: int, offset: int} $pagination
     * @return array{page: int, per_page: int, total: int, total_pages: int}
     */
    private function paginationMeta(array $pagination, int $total): array
    {
        return [
            'page' => $pagination['page'],
            'per_page' => $pagination['per_page'],
            'total' => $total,
            'total_pages' => (int) ceil($total / $pagination['per_page']),
        ];
    }

    private function parseId(string $id): ?int
    {
        $value = filter_var($id, FILTER_VALIDATE_INT);

        return $value !== false && $value > 0 ? $value : null;
    }

    private function normalizeProduct(array $product): array
    {
        return [
            'id' => (int) $product['id'],
            'name' => (string) $product['name'],
            'price' => (string) $product['price'],
            'quantity_available' => (int) $product['quantity_available'],
            'created_at' => $product['created_at'] ?? null,
            'updated_at' => $product['updated_at'] ?? null,
        ];
    }

    private function isForeignKeyViolation(PDOException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? null;
        if ($driverCode === 1451 || $driverCode === 1217) {
            return true;
        }

        return $driverCode === 19
            && str_contains(strtolower($exception->getMessage()), 'foreign key constraint failed');
    }

    private function databaseError(PDOException $exception): void
    {
        error_log($exception->getMessage());
        $this->respond(500, [
            'success' => false,
            'message' => 'A database error occurred.',
        ]);
    }

    private function notFound(string $message): void
    {
        $this->respond(404, [
            'success' => false,
            'message' => $message,
        ]);
    }

    private function respond(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
