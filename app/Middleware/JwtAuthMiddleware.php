<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\JwtService;
use App\Services\JwtConfigurationException;

final class JwtAuthMiddleware
{
    public function __construct(
        private JwtService $jwtService
    ) {
    }

    public function handle(): bool
    {
        unset($_SERVER['AUTH_USER']);
        $authorization = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (
            !is_string($authorization)
            || preg_match('/^Bearer\s+([A-Za-z0-9._-]+)$/iD', $authorization, $matches) !== 1
        ) {
            $this->respondUnauthorized();

            return false;
        }

        try {
            $user = $this->jwtService->validateToken($matches[1]);
        } catch (JwtConfigurationException $exception) {
            error_log($exception->getMessage());
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Bearer authentication is not configured.',
            ], JSON_THROW_ON_ERROR);

            return false;
        }
        if ($user === null) {
            $this->respondUnauthorized();

            return false;
        }

        $_SERVER['AUTH_USER'] = $user;

        return true;
    }

    private function respondUnauthorized(): void
    {
        http_response_code(401);
        header('WWW-Authenticate: Bearer');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'A valid Bearer token is required.',
        ], JSON_THROW_ON_ERROR);
    }
}
