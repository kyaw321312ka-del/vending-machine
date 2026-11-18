<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Repositories\ProductRepository;
use App\Routing\Route;
use App\Services\PurchaseService;
use App\Validation\ProductValidator;
use InvalidArgumentException;
use PDOException;
use RuntimeException;
use Throwable;

final class ProductsController
{
    public function __construct(
        private ProductRepository $products,
        private ProductValidator $validator,
        private PurchaseService $purchaseService
    ) {
    }

    #[Route(
        'GET',
        '/admin/products/create',
        [[AuthMiddleware::class], [RoleMiddleware::class, 'admin']]
    )]
    public function create(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session has not been started.');
        }

        $this->renderCreateForm([], [], 200);
    }

    #[Route(
        'GET',
        '/admin/products/{id}/edit',
        [[AuthMiddleware::class], [RoleMiddleware::class, 'admin']]
    )]
    public function edit(string $id): void
    {
        $productId = filter_var($id, FILTER_VALIDATE_INT);
        if ($productId === false || $productId <= 0) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Product not found.';

            return;
        }

        $product = $this->products->findById($productId);
        if ($product === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Product not found.';

            return;
        }

        $errors = [];
        $old = [];
        $this->renderEditForm($product, $errors, $old, 200);
    }

    #[Route(
        'POST',
        '/admin/products',
        [[AuthMiddleware::class], [RoleMiddleware::class, 'admin']]
    )]
    public function store(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Method not allowed.';

            return;
        }

        if (!$this->hasValidCsrfToken()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Invalid CSRF token.';

            return;
        }

        $old = [];
        foreach (['name', 'price', 'quantity_available'] as $field) {
            if (isset($_POST[$field]) && is_string($_POST[$field])) {
                $old[$field] = $_POST[$field];
            }
        }

        $errors = $this->validator->validate($old);
        if ($errors !== []) {
            $this->renderCreateForm($errors, $old, 422);

            return;
        }

        try {
            $quantity = filter_var($old['quantity_available'], FILTER_VALIDATE_INT);
            if ($quantity === false) {
                throw new RuntimeException('Validated product quantity could not be parsed.');
            }

            $this->products->create(
                trim($old['name']),
                trim($old['price']),
                $quantity
            );

            $_SESSION['_flash_success'] = 'Product created successfully.';
            http_response_code(303);
            header('Location: '.$this->basePath().'/products', true, 303);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->renderCreateForm(
                ['_general' => 'Unable to create product. Please try again.'],
                $old,
                500
            );
        }
    }

    #[Route(
        'POST',
        '/admin/products/{id}/update',
        [[AuthMiddleware::class], [RoleMiddleware::class, 'admin']]
    )]
    public function update(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Method not allowed.';

            return;
        }

        if (!$this->hasValidCsrfToken()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Invalid CSRF token.';

            return;
        }

        $productId = filter_var($id, FILTER_VALIDATE_INT);
        if ($productId === false || $productId <= 0) {
            $this->notFound();

            return;
        }

        $product = $this->products->findById($productId);
        if ($product === null) {
            $this->notFound();

            return;
        }

        $old = [];
        foreach (['name', 'price', 'quantity_available'] as $field) {
            if (isset($_POST[$field]) && is_string($_POST[$field])) {
                $old[$field] = $_POST[$field];
            }
        }

        $errors = $this->validator->validate($old);
        if ($errors !== []) {
            $this->renderEditForm($product, $errors, $old, 422);

            return;
        }

        try {
            $quantity = filter_var($old['quantity_available'], FILTER_VALIDATE_INT);
            if ($quantity === false) {
                throw new RuntimeException('Validated product quantity could not be parsed.');
            }

            if (!$this->products->update(
                $productId,
                trim($old['name']),
                trim($old['price']),
                $quantity
            )) {
                $this->notFound();

                return;
            }

            $_SESSION['_flash_success'] = 'Product updated successfully.';
            http_response_code(303);
            header('Location: '.$this->basePath().'/products', true, 303);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->renderEditForm(
                $product,
                ['_general' => 'Unable to update product. Please try again.'],
                $old,
                500
            );
        }
    }

    #[Route(
        'POST',
        '/admin/products/{id}/delete',
        [[AuthMiddleware::class], [RoleMiddleware::class, 'admin']]
    )]
    public function destroy(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Method not allowed.';

            return;
        }

        if (!$this->hasValidCsrfToken()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Invalid CSRF token.';

            return;
        }

        $productId = filter_var($id, FILTER_VALIDATE_INT);
        if ($productId === false || $productId <= 0) {
            $this->notFound();

            return;
        }

        if ($this->products->findById($productId) === null) {
            $this->notFound();

            return;
        }

        try {
            if (!$this->products->delete($productId)) {
                $this->notFound();

                return;
            }

            $_SESSION['_flash_success'] = 'Product deleted successfully.';
        } catch (PDOException $exception) {
            if ($this->isForeignKeyConstraintViolation($exception)) {
                $_SESSION['_flash_error'] = 'This product cannot be deleted because it has transaction history.';
            } else {
                error_log($exception->getMessage());
                $_SESSION['_flash_error'] = 'Unable to delete product. Please try again.';
            }
        }

        http_response_code(303);
        header('Location: '.$this->basePath().'/products', true, 303);
    }

    #[Route('GET', '/products')]
    public function index(): void
    {
        $page = $this->queryInteger('page', 1);
        $perPage = $this->queryInteger('per_page', 10);
        $sortBy = $_GET['sort_by'] ?? 'id';
        $sortDirection = $_GET['sort_direction'] ?? 'ASC';

        if ($page === null || $perPage === null || !is_string($sortBy) || !is_string($sortDirection)) {
            $this->badRequest();

            return;
        }

        $perPage = min($perPage, 100);
        $totalProducts = $this->products->countAll();
        $totalPages = max(1, (int) ceil($totalProducts / $perPage));
        $page = min($page, $totalPages);

        try {
            $products = $this->products->getAll(
                $perPage,
                ($page - 1) * $perPage,
                $sortBy,
                $sortDirection
            );
        } catch (InvalidArgumentException) {
            $this->badRequest();

            return;
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = $this->basePath();
        $assetUrl = ($basePath === '')
            ? '/assets/css/output.css'
            : $basePath . '/assets/css/output.css';
        $successMessage = null;
        $errorMessage = null;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['_flash_success'])) {
            $successMessage = is_string($_SESSION['_flash_success'])
                ? $_SESSION['_flash_success']
                : null;
            unset($_SESSION['_flash_success']);
        }
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['_flash_error'])) {
            $errorMessage = is_string($_SESSION['_flash_error'])
                ? $_SESSION['_flash_error']
                : null;
            unset($_SESSION['_flash_error']);
        }

        header('Content-Type: text/html; charset=utf-8');

        require dirname(__DIR__, 2) . '/resources/views/products/index.php';
    }

    private function renderCreateForm(array $errors, array $old, int $status): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session has not been started.');
        }

        $csrfToken = $this->csrfToken();
        $basePath = $this->basePath();
        $assetUrl = $basePath === ''
            ? '/assets/css/output.css'
            : $basePath . '/assets/css/output.css';

        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__, 2) . '/resources/views/admin/products/create.php';
    }

    private function renderEditForm(array $product, array $errors, array $old, int $status): void
    {
        $csrfToken = $this->csrfToken();
        $basePath = $this->basePath();
        $assetUrl = $basePath === ''
            ? '/assets/css/output.css'
            : $basePath . '/assets/css/output.css';

        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__, 2) . '/resources/views/admin/products/edit.php';
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Product not found.';
    }

    private function isForeignKeyConstraintViolation(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        if (!is_array($errorInfo) || ($errorInfo[0] ?? null) !== '23000') {
            return false;
        }

        $driverCode = (int) ($errorInfo[1] ?? 0);
        if ($driverCode === 1451) {
            return true;
        }

        return $driverCode === 19
            && str_contains(strtolower((string) ($errorInfo[2] ?? '')), 'foreign key constraint');
    }

    private function hasValidCsrfToken(): bool
    {
        $expected = session_status() === PHP_SESSION_ACTIVE
            ? ($_SESSION['_csrf_token'] ?? null)
            : null;
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf_token'] ?? null;

        return is_string($expected)
            && is_string($provided)
            && hash_equals($expected, $provided);
    }

    private function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session has not been started.');
        }

        if (
            !isset($_SESSION['_csrf_token'])
            || !is_string($_SESSION['_csrf_token'])
            || preg_match('/^[a-f0-9]{64}$/D', $_SESSION['_csrf_token']) !== 1
        ) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    private function basePath(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(dirname($scriptName), '/');

        return $basePath === '.' || $basePath === '/' ? '' : $basePath;
    }

    private function queryInteger(string $key, int $default): ?int
    {
        $value = $_GET[$key] ?? (string) $default;
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer !== false && $integer > 0 ? $integer : null;
    }

    private function badRequest(): void
    {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Invalid product listing parameters.';
    }
}
