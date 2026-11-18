<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Routing\Route;
use App\Routing\Router;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        RouterTestController::$calls = [];
        http_response_code(200);
    }

    public function testControllerAttributesRegisterMultipleRoutes(): void
    {
        $router = $this->createRouter();
        $router->registerController(RouterTestController::class);

        $router->dispatch('GET', '/health');
        $router->dispatch('POST', '/health');

        self::assertSame(['health', 'health'], RouterTestController::$calls);
    }

    public function testDuplicateMethodAndPathIsRejected(): void
    {
        $router = $this->createRouter();
        $router->addRoute('GET', '/health/', RouterTestController::class, 'health');

        $this->expectException(RuntimeException::class);
        $router->addRoute('get', '/health', RouterTestController::class, 'health');
    }

    public function testDispatchMatchesRouteAndPassesNamedParameters(): void
    {
        $router = $this->createRouter();
        $router->registerController(RouterTestController::class);

        $router->dispatch('GET', '/products/15?include=stock');

        self::assertSame([['show', '15']], RouterTestController::$calls);
    }

    public function testMethodMismatchReturns405AndUnknownPathReturns404(): void
    {
        $router = $this->createRouter();
        $router->registerController(RouterTestController::class);

        [$methodResponse, $methodStatus] = $this->dispatchWithResponse($router, 'PATCH', '/health');
        self::assertSame(405, $methodStatus);
        self::assertSame('Method not allowed.', $methodResponse['message']);

        [$notFoundResponse, $notFoundStatus] = $this->dispatchWithResponse($router, 'GET', '/missing');
        self::assertSame(404, $notFoundStatus);
        self::assertSame('Route not found.', $notFoundResponse['message']);
    }

    public function testMiddlewareCanStopControllerExecution(): void
    {
        $router = $this->createRouter();
        $router->addRoute(
            'GET',
            '/blocked',
            RouterTestController::class,
            'health',
            [[RouterTestMiddleware::class, false]]
        );

        $router->dispatch('GET', '/blocked');

        self::assertSame([], RouterTestController::$calls);
        self::assertSame(1, RouterTestMiddleware::$calls);
    }

    private function createRouter(): Router
    {
        return new Router(
            static fn (string $class): object => new $class(),
            static fn (string $class): object => new $class()
        );
    }

    private function dispatchWithResponse(Router $router, string $method, string $uri): array
    {
        ob_start();
        $router->dispatch($method, $uri);
        $body = ob_get_clean();

        return [json_decode($body, true, 512, JSON_THROW_ON_ERROR), http_response_code()];
    }
}

final class RouterTestController
{
    public static array $calls = [];

    #[Route('GET', '/health')]
    #[Route('POST', '/health')]
    public function health(): void
    {
        self::$calls[] = 'health';
    }

    #[Route('GET', '/products/{id}')]
    public function show(string $id): void
    {
        self::$calls[] = ['show', $id];
    }
}

final class RouterTestMiddleware
{
    public static int $calls = 0;

    public function handle(bool $allowed): bool
    {
        self::$calls++;

        return $allowed;
    }
}
