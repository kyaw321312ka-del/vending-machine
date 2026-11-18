<?php

declare(strict_types=1);

namespace App\Middleware;

use InvalidArgumentException;

final class JwtRoleMiddleware
{
    public function handle(string $requiredRole): bool
    {
        if (!in_array($requiredRole, ['admin', 'user'], true)) {
            throw new InvalidArgumentException('Required role must be admin or user.');
        }

        $user = $_SERVER['AUTH_USER'] ?? null;
        if (
            !is_array($user)
            || !isset($user['id'], $user['role'])
            || !is_int($user['id'])
            || $user['id'] <= 0
            || !in_array($user['role'], ['admin', 'user'], true)
        ) {
            $this->respond(401, 'Authentication required.');

            return false;
        }

        if ($user['role'] !== $requiredRole) {
            $this->respond(403, 'You do not have permission to access this resource.');

            return false;
        }

        return true;
    }

    private function respond(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
        ], JSON_THROW_ON_ERROR);
    }
}
