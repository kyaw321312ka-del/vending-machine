<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Http\JsonInputException;
use App\Http\JsonRequest;
use App\Routing\Route;
use App\Services\AuthService;
use App\Services\JwtConfigurationException;
use App\Services\JwtService;
use PDOException;

final class AuthApiController
{
    private JsonRequest $jsonRequest;

    public function __construct(
        private AuthService $auth,
        private JwtService $jwt,
        ?JsonRequest $jsonRequest = null
    ) {
        $this->jsonRequest = $jsonRequest ?? new JsonRequest();
    }

    #[Route('POST', '/api/auth/login')]
    public function login(): void
    {
        try {
            $input = $this->jsonRequest->body();
        } catch (JsonInputException $exception) {
            $this->respond($exception->statusCode, [
                'success' => false,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        if (
            !isset($input['email'], $input['password'])
            || !is_string($input['email'])
            || !is_string($input['password'])
        ) {
            $this->respond(422, [
                'success' => false,
                'message' => 'Email and password are required.',
            ]);

            return;
        }

        try {
            $user = $this->auth->attemptLogin($input['email'], $input['password']);
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $this->respond(500, [
                'success' => false,
                'message' => 'A database error occurred.',
            ]);

            return;
        }
        if ($user === null) {
            $this->respond(401, [
                'success' => false,
                'message' => 'Invalid email or password.',
            ]);

            return;
        }

        try {
            $token = $this->jwt->createToken($user['id'], $user['role']);
        } catch (JwtConfigurationException $exception) {
            error_log($exception->getMessage());
            $this->respond(503, [
                'success' => false,
                'message' => 'Bearer authentication is not configured.',
            ]);

            return;
        }

        $this->respond(200, [
            'success' => true,
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token,
                'expires_in' => 3600,
                'user' => $user,
            ],
        ]);
    }

    private function respond(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
