<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Routing\Route;
use App\Services\AuthService;
use InvalidArgumentException;
use Throwable;

final class AuthController
{
    private const CSRF_SESSION_KEY = '_csrf_token';

    public function __construct(
        private AuthService $authService
    ) {
    }

    #[Route('GET', '/csrf-token')]
    public function csrfToken(): void
    {
        if (!$this->requireMethod('GET')) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->respond(500, [
                'success' => false,
                'message' => 'An unexpected error occurred.',
            ]);

            return;
        }

        try {
            if (!isset($_SESSION[self::CSRF_SESSION_KEY])) {
                $_SESSION[self::CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
            }

            header('Cache-Control: no-store');
            $this->respond(200, [
                'success' => true,
                'data' => [
                    'csrf_token' => $_SESSION[self::CSRF_SESSION_KEY],
                ],
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->respond(500, [
                'success' => false,
                'message' => 'An unexpected error occurred.',
            ]);
        }
    }

    #[Route('POST', '/register')]
    public function register(): void
    {
        if (!$this->requireMethod('POST') || !$this->requireCsrfToken()) {
            return;
        }

        $name = $this->postString('name');
        $email = $this->postString('email');
        $password = $this->postString('password');

        if ($name === null || $email === null || $password === null) {
            $this->respond(422, [
                'success' => false,
                'message' => 'Name, email, and password are required.',
            ]);

            return;
        }

        try {
            $userId = $this->authService->register(trim($name), trim($email), $password);
            $this->respond(201, [
                'success' => true,
                'message' => 'Registration successful.',
                'data' => [
                    'user_id' => $userId,
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(422, [
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->respond(500, [
                'success' => false,
                'message' => 'An unexpected error occurred.',
            ]);
        }
    }

    #[Route('POST', '/login')]
    public function login(): void
    {
        if (!$this->requireMethod('POST') || !$this->requireCsrfToken()) {
            return;
        }

        $email = $this->postString('email');
        $password = $this->postString('password');

        if ($email === null || $password === null) {
            $this->respond(422, [
                'success' => false,
                'message' => 'Email and password are required.',
            ]);

            return;
        }

        try {
            $user = $this->authService->attemptLogin(trim($email), $password);

            if ($user === null) {
                $this->respond(401, [
                    'success' => false,
                    'message' => 'Invalid email or password.',
                ]);

                return;
            }

            $this->authService->login($user);
            $this->respond(200, [
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'user' => $user,
                ],
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->respond(500, [
                'success' => false,
                'message' => 'An unexpected error occurred.',
            ]);
        }
    }

    #[Route('POST', '/logout')]
    public function logout(): void
    {
        if (!$this->requireMethod('POST') || !$this->requireCsrfToken()) {
            return;
        }

        try {
            $this->authService->logout();
            $this->respond(200, [
                'success' => true,
                'message' => 'Logout successful.',
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->respond(500, [
                'success' => false,
                'message' => 'An unexpected error occurred.',
            ]);
        }
    }

    #[Route('GET', '/me', middleware: [[AuthMiddleware::class]])]
    public function me(): void
    {
        if (!$this->requireMethod('GET')) {
            return;
        }

        $user = $this->authService->currentUser();

        if ($user === null) {
            $this->respond(401, [
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);

            return;
        }

        $this->respond(200, [
            'success' => true,
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    private function requireMethod(string $method): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === $method) {
            return true;
        }

        header('Allow: '.$method);
        $this->respond(405, [
            'success' => false,
            'message' => 'Method not allowed.',
        ]);

        return false;
    }

    private function requireCsrfToken(): bool
    {
        $expected = session_status() === PHP_SESSION_ACTIVE
            ? ($_SESSION[self::CSRF_SESSION_KEY] ?? null)
            : null;
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf_token'] ?? null;

        if (
            is_string($expected)
            && is_string($provided)
            && hash_equals($expected, $provided)
        ) {
            return true;
        }

        $this->respond(403, [
            'success' => false,
            'message' => 'Invalid CSRF token.',
        ]);

        return false;
    }

    private function postString(string $key): ?string
    {
        return isset($_POST[$key]) && is_string($_POST[$key])
            ? $_POST[$key]
            : null;
    }

    private function respond(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
