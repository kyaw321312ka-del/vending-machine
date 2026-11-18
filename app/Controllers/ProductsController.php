<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\ProductRepositoryInterface;
use App\Contracts\PurchaseServiceInterface;
use App\Contracts\TransactionRepositoryInterface;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Routing\Route;
use App\Validation\ProductValidator;
use DomainException;
use InvalidArgumentException;
use PDOException;
use RuntimeException;
use Throwable;

final class ProductsController
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductValidator $validator,
        private PurchaseServiceInterface $purchaseService,
        private TransactionRepositoryInterface $transactions
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
        foreach (['name', 'price', 'quantity_available', 'is_enabled'] as $field) {
            if (isset($_POST[$field]) && is_string($_POST[$field])) {
                $old[$field] = $_POST[$field];
            }
        }
        $old['is_enabled'] = $old['is_enabled'] ?? '0';

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
                $quantity,
                $old['is_enabled'] === '1'
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
        foreach (['name', 'price', 'quantity_available', 'is_enabled'] as $field) {
            if (isset($_POST[$field]) && is_string($_POST[$field])) {
                $old[$field] = $_POST[$field];
            }
        }
        $old['is_enabled'] = $old['is_enabled'] ?? '0';

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
                $quantity,
                $old['is_enabled'] === '1'
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

    #[Route('GET', '/')]
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
        $currentUser = session_status() === PHP_SESSION_ACTIVE
            ? ($_SESSION['user'] ?? null)
            : null;
        $isAdmin = is_array($currentUser) && ($currentUser['role'] ?? null) === 'admin';
        $totalProducts = $isAdmin
            ? $this->products->countAll()
            : $this->products->countAvailable();
        $totalPages = max(1, (int) ceil($totalProducts / $perPage));
        $page = min($page, $totalPages);

        try {
            $products = $isAdmin
                ? $this->products->getAll(
                    $perPage,
                    ($page - 1) * $perPage,
                    $sortBy,
                    $sortDirection
                )
                : $this->products->getAvailable(
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
        $csrfToken = is_array($currentUser) ? $this->csrfToken() : '';
        $assetUrl = ($basePath === '')
            ? '/assets/css/output.css'
            : $basePath . '/assets/css/output.css';
        $successMessage = $this->takeFlash('_flash_success');
        $errorMessage = $this->takeFlash('_flash_error');

        header('Content-Type: text/html; charset=utf-8');

        require dirname(__DIR__, 2) . '/resources/views/products/index.php';
    }

    #[Route('GET', '/products/{id}')]
    public function show(string $id): void
    {
        $productId = filter_var($id, FILTER_VALIDATE_INT);
        if ($productId === false || $productId <= 0) {
            $this->notFound();

            return;
        }

        $currentUser = session_status() === PHP_SESSION_ACTIVE
            ? ($_SESSION['user'] ?? null)
            : null;
        if (!is_array($currentUser)) {
            $currentUser = null;
        }
        $isAdmin = ($currentUser['role'] ?? null) === 'admin';

        $product = $this->products->findById($productId);
        if (
            $product === null
            || (!$isAdmin && (
                (int) ($product['is_enabled'] ?? 0) !== 1
                || (int) ($product['quantity_available'] ?? 0) <= 0
            ))
        ) {
            $this->notFound();

            return;
        }

        if (
            !is_array($currentUser)
            || !isset($currentUser['id'])
            || !is_int($currentUser['id'])
            || $currentUser['id'] <= 0
        ) {
            $currentUser = null;
        }

        $csrfToken = $currentUser === null ? '' : $this->csrfToken();
        $errors = [];
        $old = [];
        $successMessage = $this->takeFlash('_flash_success');
        $errorMessage = $this->takeFlash('_flash_error');
        $basePath = $this->basePath();
        $assetUrl = $basePath === ''
            ? '/assets/css/output.css'
            : $basePath . '/assets/css/output.css';

        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__, 2) . '/resources/views/products/show.php';
    }

    #[Route('POST', '/products/{id}/purchase', [[AuthMiddleware::class]])]
    public function purchase(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Method not allowed.';

            return;
        }

        $currentUser = $_SESSION['user'] ?? null;
        if (
            !is_array($currentUser)
            || !isset($currentUser['id'])
            || !is_int($currentUser['id'])
            || $currentUser['id'] <= 0
            || ($currentUser['role'] ?? null) !== 'user'
        ) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Only customer accounts can purchase products.';

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

        $quantityInput = $_POST['quantity'] ?? null;
        $quantity = is_string($quantityInput)
            ? filter_var($quantityInput, FILTER_VALIDATE_INT)
            : false;
        if ($quantity === false || $quantity <= 0) {
            $this->renderProductDetails(
                $product,
                ['quantity' => 'Quantity must be a positive whole number.'],
                ['quantity' => is_string($quantityInput) ? $quantityInput : ''],
                422
            );

            return;
        }

        $userId = $currentUser['id'];
        $redirectToListing = false;
        try {
            $purchase = $this->purchaseService->purchase($userId, $productId, $quantity);
            $redirectToListing = $purchase['remaining_quantity'] === 0;
            $_SESSION['_flash_success'] = sprintf(
                'Purchase successful: %d × %s for %s.',
                $purchase['quantity'],
                $purchase['product_name'],
                $purchase['total_price']
            );
        } catch (DomainException $exception) {
            $redirectToListing = in_array($exception->getMessage(), [
                'Product is not available for purchase.',
                'Insufficient product inventory.',
            ], true);
            $_SESSION['_flash_error'] = match ($exception->getMessage()) {
                'Insufficient product inventory.' => 'There is not enough stock to complete this purchase.',
                'Product is not available for purchase.' => 'This product is not currently available for purchase.',
                default => 'Unable to complete this purchase.',
            };
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $_SESSION['_flash_error'] = 'Unable to complete this purchase. Please try again.';
        }

        http_response_code(303);
        $redirectPath = $redirectToListing
            ? $this->basePath().'/products'
            : $this->basePath().'/products/'.$productId;
        header('Location: '.$redirectPath, true, 303);
    }

    #[Route('GET', '/transactions', [[AuthMiddleware::class]])]
    public function history(): void
    {
        $page = $this->queryInteger('page', 1);
        $perPage = $this->queryInteger('per_page', 10);
        if ($page === null || $perPage === null) {
            $this->badRequest();

            return;
        }

        $perPage = min($perPage, 100);
        $currentUser = $_SESSION['user'] ?? null;
        $isAdmin = is_array($currentUser) && ($currentUser['role'] ?? null) === 'admin';
        $userId = is_array($currentUser) && is_int($currentUser['id'] ?? null)
            ? $currentUser['id']
            : 0;
        $totalTransactions = $isAdmin
            ? $this->transactions->countAll()
            : $this->transactions->countByUserId($userId);
        $totalPages = max(1, (int) ceil($totalTransactions / $perPage));
        $page = min($page, $totalPages);
        $transactions = $isAdmin
            ? $this->transactions->getAll($perPage, ($page - 1) * $perPage)
            : $this->transactions->getByUserId($userId, $perPage, ($page - 1) * $perPage);
        $basePath = $this->basePath();
        $csrfToken = $this->csrfToken();
        $successMessage = $this->takeFlash('_flash_success');
        $errorMessage = $this->takeFlash('_flash_error');

        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__, 2) . '/resources/views/products/history.php';
    }

    private function renderProductDetails(array $product, array $errors, array $old, int $status): void
    {
        $currentUser = $_SESSION['user'] ?? null;
        $csrfToken = $this->csrfToken();
        $successMessage = $this->takeFlash('_flash_success');
        $errorMessage = $this->takeFlash('_flash_error');
        $basePath = $this->basePath();
        $assetUrl = $basePath === ''
            ? '/assets/css/output.css'
            : $basePath . '/assets/css/output.css';

        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__, 2) . '/resources/views/products/show.php';
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

    private function takeFlash(string $key): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !array_key_exists($key, $_SESSION)) {
            return null;
        }

        $message = is_string($_SESSION[$key]) ? $_SESSION[$key] : null;
        unset($_SESSION[$key]);

        return $message;
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
