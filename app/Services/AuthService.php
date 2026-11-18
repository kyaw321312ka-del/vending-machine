<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use InvalidArgumentException;
use RuntimeException;

final class AuthService
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    public function register(string $name, string $email, string $password): int
    {
        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        if ($this->userRepository->emailExists($email)) {
            throw new InvalidArgumentException('Email address is already registered.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Unable to hash password.');
        }

        return $this->userRepository->create($name, $email, $passwordHash);
    }

    public function attemptLogin(string $email, string $password): ?array
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $user = $this->userRepository->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password'])) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }

    public function login(array $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session must be started before login.');
        }

        if (
            !isset($user['id'], $user['name'], $user['email'], $user['role'])
            || !is_int($user['id'])
            || $user['id'] <= 0
            || !is_string($user['name'])
            || !is_string($user['email'])
            || !is_string($user['role'])
            || !in_array($user['role'], ['admin', 'user'], true)
        ) {
            throw new InvalidArgumentException('A valid authenticated user is required.');
        }

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Unable to regenerate the session ID.');
        }

        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }

    public function currentUser(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        return $_SESSION['user'] ?? null;
    }

    public function isAuthenticated(): bool
    {
        return $this->currentUser() !== null;
    }

    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
