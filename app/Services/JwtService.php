<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class JwtService
{
    public function __construct(
        private string $secret,
        private string $issuer = 'vending-machine'
    ) {
    }

    public function createToken(int $userId, string $role, int $ttlSeconds = 3600): string
    {
        $this->assertSecret();
        if ($userId <= 0 || !in_array($role, ['admin', 'user'], true)) {
            throw new InvalidArgumentException('A valid user ID and role are required.');
        }
        if ($ttlSeconds < 1 || $ttlSeconds > 86400) {
            throw new InvalidArgumentException('Token lifetime must be between 1 and 86400 seconds.');
        }

        $issuedAt = time();
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = [
            'iss' => $this->issuer,
            'sub' => (string) $userId,
            'role' => $role,
            'iat' => $issuedAt,
            'exp' => $issuedAt + $ttlSeconds,
        ];
        $unsignedToken = $this->encodeSegment($header).'.'.$this->encodeSegment($payload);
        $signature = hash_hmac('sha256', $unsignedToken, $this->secret, true);

        return $unsignedToken.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * @return array{id: int, role: string}|null
     */
    public function validateToken(string $token): ?array
    {
        $this->assertSecret();
        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $headerJson = $this->base64UrlDecode($encodedHeader);
        $payloadJson = $this->base64UrlDecode($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);
        if ($headerJson === null || $payloadJson === null || $signature === null) {
            return null;
        }

        try {
            $header = json_decode($headerJson, true, 512, JSON_THROW_ON_ERROR);
            $claims = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (
            !is_array($header)
            || ($header['typ'] ?? null) !== 'JWT'
            || ($header['alg'] ?? null) !== 'HS256'
            || !is_array($claims)
        ) {
            return null;
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $encodedHeader.'.'.$encodedPayload,
            $this->secret,
            true
        );
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $userId = isset($claims['sub']) && is_string($claims['sub'])
            ? filter_var($claims['sub'], FILTER_VALIDATE_INT)
            : false;
        $now = time();
        if (
            ($claims['iss'] ?? null) !== $this->issuer
            || $userId === false
            || $userId <= 0
            || !in_array($claims['role'] ?? null, ['admin', 'user'], true)
            || !is_int($claims['iat'] ?? null)
            || !is_int($claims['exp'] ?? null)
            || $claims['iat'] > $now + 60
            || $claims['exp'] <= $now
            || $claims['exp'] <= $claims['iat']
            || $claims['exp'] - $claims['iat'] > 86400
        ) {
            return null;
        }

        return [
            'id' => (int) $userId,
            'role' => $claims['role'],
        ];
    }

    private function assertSecret(): void
    {
        if (strlen($this->secret) < 32) {
            throw new JwtConfigurationException('JWT_SECRET must contain at least 32 bytes.');
        }
    }

    private function encodeSegment(array $value): string
    {
        return $this->base64UrlEncode(json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
