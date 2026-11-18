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
use RuntimeException;

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

        if (
            !isset($_SESSION['_csrf_token'])
            || !is_string($_SESSION['_csrf_token'])
            || preg_match('/^[a-f0-9]{64}$/D', $_SESSION['_csrf_token']) !== 1
        ) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        $csrfToken = $_SESSION['_csrf_token'];
        $errors = [];
        $old = [];

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $assetUrl = ($basePath === '' || $basePath === '.' || $basePath === '/')
            ? '/assets/css/output.css'
            : $basePath . '/assets/css/output.css';

        header('Content-Type: text/html; charset=utf-8');

        require dirname(__DIR__, 2) . '/resources/views/admin/products/create.php';
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
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $assetUrl = ($basePath === '' || $basePath === '.' || $basePath === '/')
            ? '/assets/css/output.css'
            : $basePath . '/assets/css/output.css';

        header('Content-Type: text/html; charset=utf-8');

        require dirname(__DIR__, 2) . '/resources/views/products/index.php';
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
