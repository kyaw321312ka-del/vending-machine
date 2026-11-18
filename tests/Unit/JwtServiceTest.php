<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\JwtConfigurationException;
use App\Services\JwtService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    private const SECRET = 'test-secret-with-at-least-thirty-two-bytes';

    public function testIssuedTokenIncludesValidatedUserIdentityAndRole(): void
    {
        $service = new JwtService(self::SECRET);
        $token = $service->createToken(42, 'admin');

        self::assertSame(['id' => 42, 'role' => 'admin'], $service->validateToken($token));
    }

    public function testInvalidSignatureIssuerAudienceAndExpiredClaimsAreRejected(): void
    {
        $service = new JwtService(self::SECRET);
        $token = $service->createToken(42, 'user');
        $segments = explode('.', $token);
        $segments[2] = str_repeat('A', strlen($segments[2]));

        self::assertNull($service->validateToken(implode('.', $segments)));
        self::assertNull($service->validateToken($this->signedToken([
            'iss' => 'other-issuer',
            'aud' => 'vending-machine-api',
            'sub' => '42',
            'role' => 'user',
            'iat' => time(),
            'exp' => time() + 60,
        ])));
        self::assertNull($service->validateToken($this->signedToken([
            'iss' => 'vending-machine',
            'aud' => 'another-api',
            'sub' => '42',
            'role' => 'user',
            'iat' => time(),
            'exp' => time() + 60,
        ])));
        self::assertNull($service->validateToken($this->signedToken([
            'iss' => 'vending-machine',
            'aud' => 'vending-machine-api',
            'sub' => '42',
            'role' => 'user',
            'iat' => time() - 120,
            'exp' => time() - 1,
        ])));
        self::assertNull($service->validateToken($this->signedToken([
            'iss' => 'vending-machine',
            'aud' => 'vending-machine-api',
            'sub' => '42',
            'role' => 'user',
            'iat' => -1,
            'exp' => time() + 60,
        ])));
    }

    public function testTokenCreationRejectsInvalidIdentityAndLifetime(): void
    {
        $service = new JwtService(self::SECRET);

        try {
            $service->createToken(0, 'user');
            self::fail('Expected an invalid user ID to be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $service->createToken(1, 'user', 86401);
    }

    public function testWeakSecretFailsExplicitly(): void
    {
        $this->expectException(JwtConfigurationException::class);
        (new JwtService('too-short'))->createToken(1, 'user');
    }

    private function signedToken(array $claims): string
    {
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$payload;
        $signature = hash_hmac('sha256', $unsigned, self::SECRET, true);

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
