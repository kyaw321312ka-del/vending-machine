<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Controllers\AuthController;
use App\Controllers\Api\AuthApiController;
use App\Controllers\Api\ProductsApiController;
use App\Controllers\ProductsController;
use App\Controllers\WebAuthController;
use App\Database\Database;
use App\Middleware\AuthMiddleware;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\JwtRoleMiddleware;
use App\Middleware\RoleMiddleware;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Routing\Router;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\PurchaseService;
use App\Validation\ProductValidator;
use RuntimeException;

final class Application
{
    private Router $router;

    public function __construct()
    {
        $rootPath = dirname(__DIR__, 2);
        $pdo = Database::getConnection();
        $productRepository = new ProductRepository($pdo);
        $transactionRepository = new TransactionRepository($pdo);
        $userRepository = new UserRepository($pdo);
        $authService = new AuthService($userRepository);
        $productValidator = new ProductValidator();
        $purchaseService = new PurchaseService($pdo, $productRepository, $transactionRepository);
        $jwtConfig = require $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'jwt.php';
        $jwtService = new JwtService($jwtConfig['secret'], $jwtConfig['issuer']);

        $controllerResolver = static function (string $controllerClass) use (
            $authService,
            $jwtService,
            $productRepository,
            $productValidator,
            $purchaseService,
            $transactionRepository
        ): object {
            return match ($controllerClass) {
                AuthController::class => new AuthController($authService),
                AuthApiController::class => new AuthApiController($authService, $jwtService),
                WebAuthController::class => new WebAuthController($authService),
                ProductsController::class => new ProductsController(
                    $productRepository,
                    $productValidator,
                    $purchaseService,
                    $transactionRepository
                ),
                ProductsApiController::class => new ProductsApiController(
                    $productRepository,
                    $productValidator,
                    $purchaseService,
                    $transactionRepository
                ),
                default => throw new RuntimeException(
                    "Controller resolver is not configured for {$controllerClass}."
                ),
            };
        };

        $middlewareResolver = static function (string $middlewareClass) use ($jwtService): object {
            return match ($middlewareClass) {
                AuthMiddleware::class => new AuthMiddleware(),
                RoleMiddleware::class => new RoleMiddleware(),
                JwtAuthMiddleware::class => new JwtAuthMiddleware($jwtService),
                JwtRoleMiddleware::class => new JwtRoleMiddleware(),
                default => throw new RuntimeException(
                    "Middleware resolver is not configured for {$middlewareClass}."
                ),
            };
        };

        $this->router = new Router($controllerResolver, $middlewareResolver);

        foreach (['routes/web.php', 'routes/api.php'] as $routeFile) {
            $controllers = require $rootPath . DIRECTORY_SEPARATOR . $routeFile;
            if (!is_array($controllers)) {
                throw new RuntimeException("Route file must return an array: {$routeFile}");
            }

            foreach ($controllers as $controllerClass) {
                if (!is_string($controllerClass)) {
                    throw new RuntimeException("Invalid controller entry in route file: {$routeFile}");
                }

                $this->router->registerController($controllerClass);
            }
        }
    }

    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(dirname($scriptName), '/');

        if ($basePath !== '' && $basePath !== '.' && $basePath !== '/') {
            if ($path === $basePath) {
                $path = '/';
            } elseif (str_starts_with($path, $basePath . '/')) {
                $path = substr($path, strlen($basePath));
            }
        }

        $this->router->dispatch(is_string($method) ? $method : 'GET', $path);
    }
}
