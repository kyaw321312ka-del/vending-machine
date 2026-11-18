<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Routing\Route;
use App\Services\AuthService;
use InvalidArgumentException;
use RuntimeException;

final class WebAuthController
{
    public function __construct(
        private AuthService $authService
    ) {
    }

    #[Route('GET', '/auth/login')]
    public function showLogin(): void
    {
        $this->render('login.php', 'Sign in', [
            'errors' => [],
            'old' => [],
        ], 200);
    }

    #[Route('POST', '/auth/login')]
    public function login(): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->render('login.php', 'Sign in', [
                'errors' => ['_general' => 'Your session expired. Please try again.'],
                'old' => $this->oldInput(['email']),
            ], 403);

            return;
        }

        $email = $this->postString('email');
        $password = $this->postString('password');
        if ($email === null || $password === null || trim($email) === '' || $password === '') {
            $this->render('login.php', 'Sign in', [
                'errors' => ['_general' => 'Email and password are required.'],
                'old' => $this->oldInput(['email']),
            ], 422);

            return;
        }

        try {
            $user = $this->authService->attemptLogin(trim($email), $password);
            if ($user === null) {
                $this->render('login.php', 'Sign in', [
                    'errors' => ['_general' => 'Invalid email or password.'],
                    'old' => $this->oldInput(['email']),
                ], 422);

                return;
            }

            $this->authService->login($user);
            $_SESSION['_flash_success'] = 'Login successful.';
            $this->redirect('/products');
        } catch (RuntimeException $exception) {
            error_log($exception->getMessage());
            $this->render('login.php', 'Sign in', [
                'errors' => ['_general' => 'Unable to sign in right now. Please try again.'],
                'old' => $this->oldInput(['email']),
            ], 500);
        }
    }

    #[Route('GET', '/auth/register')]
    public function showRegister(): void
    {
        $this->render('register.php', 'Create account', [
            'errors' => [],
            'old' => [],
        ], 200);
    }

    #[Route('POST', '/auth/register')]
    public function register(): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->render('register.php', 'Create account', [
                'errors' => ['_general' => 'Your session expired. Please try again.'],
                'old' => $this->oldInput(['name', 'email']),
            ], 403);

            return;
        }

        $name = $this->postString('name');
        $email = $this->postString('email');
        $password = $this->postString('password');
        if ($name === null || $email === null || $password === null) {
            $this->render('register.php', 'Create account', [
                'errors' => ['_general' => 'Name, email, and password are required.'],
                'old' => $this->oldInput(['name', 'email']),
            ], 422);

            return;
        }

        try {
            $this->authService->register(trim($name), trim($email), $password);
            $_SESSION['_flash_success'] = 'Registration successful. Please sign in.';
            $this->redirect('/auth/login');
        } catch (InvalidArgumentException $exception) {
            $this->render('register.php', 'Create account', [
                'errors' => ['_general' => $exception->getMessage()],
                'old' => $this->oldInput(['name', 'email']),
            ], 422);
        } catch (RuntimeException $exception) {
            error_log($exception->getMessage());
            $this->render('register.php', 'Create account', [
                'errors' => ['_general' => 'Unable to create your account right now. Please try again.'],
                'old' => $this->oldInput(['name', 'email']),
            ], 500);
        }
    }

    #[Route('POST', '/auth/logout')]
    public function logout(): void
    {
        if (!$this->hasValidCsrfToken()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Invalid CSRF token.';

            return;
        }

        try {
            $this->authService->logout();
            $this->redirect('/auth/login');
        } catch (RuntimeException $exception) {
            error_log($exception->getMessage());
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Unable to sign out right now.';
        }
    }

    private function render(string $view, string $title, array $data, int $status): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session has not been started.');
        }

        $errors = $data['errors'];
        $old = $data['old'];
        $basePath = $this->basePath();
        $assetUrl = $basePath . '/assets/css/output.css';
        $currentUser = $this->authService->currentUser();
        $layoutSuccessMessage = $this->takeFlash('_flash_success');
        $csrfToken = $this->csrfToken();

        ob_start();
        require dirname(__DIR__, 2) . '/resources/views/auth/' . $view;
        $content = ob_get_clean();

        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__, 2) . '/resources/views/layouts/app.php';
    }

    private function hasValidCsrfToken(): bool
    {
        $expected = session_status() === PHP_SESSION_ACTIVE
            ? ($_SESSION['_csrf_token'] ?? null)
            : null;
        $provided = $_POST['_csrf_token'] ?? null;

        return is_string($expected)
            && is_string($provided)
            && hash_equals($expected, $provided);
    }

    private function csrfToken(): string
    {
        if (
            !isset($_SESSION['_csrf_token'])
            || !is_string($_SESSION['_csrf_token'])
            || preg_match('/^[a-f0-9]{64}$/D', $_SESSION['_csrf_token']) !== 1
        ) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    private function postString(string $key): ?string
    {
        return isset($_POST[$key]) && is_string($_POST[$key])
            ? $_POST[$key]
            : null;
    }

    private function oldInput(array $fields): array
    {
        $old = [];
        foreach ($fields as $field) {
            $value = $this->postString($field);
            if ($value !== null) {
                $old[$field] = $value;
            }
        }

        return $old;
    }

    private function takeFlash(string $key): ?string
    {
        $message = isset($_SESSION[$key]) && is_string($_SESSION[$key])
            ? $_SESSION[$key]
            : null;
        unset($_SESSION[$key]);

        return $message;
    }

    private function basePath(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(dirname($scriptName), '/');

        return $basePath === '.' || $basePath === '/' ? '' : $basePath;
    }

    private function redirect(string $path): void
    {
        http_response_code(303);
        header('Location: '.$this->basePath().$path, true, 303);
    }
}
