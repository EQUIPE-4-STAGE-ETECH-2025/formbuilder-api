<?php

namespace App\Tests\Service;

use App\Service\BlackListedTokenService;
use App\Service\JwtService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class JwtServiceTest extends TestCase
{
    private JwtService $jwtService;
    private string $secretKey = 'test_secret';
    private int $tokenTtl = 60;

    protected function setUp(): void
    {
        $this->jwtService = new JwtService($this->secretKey, $this->tokenTtl, 'HS256');
    }

    public function testGenerateToken(): void
    {
        $payload = ['id' => 123, 'email' => 'test@example.com'];
        $token = $this->jwtService->generateToken($payload);

        $this->assertIsString($token);
        $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));

        $this->assertEquals(123, $decoded->id);
        $this->assertEquals('test@example.com', $decoded->email);
        $this->assertTrue(isset($decoded->iat), 'Le champ "iat" est manquant.');
        $this->assertTrue(isset($decoded->exp), 'Le champ "exp" est manquant.');
    }

    public function testValidateToken(): void
    {
        $payload = ['id' => 1, 'email' => 'user@example.com'];
        $token = $this->jwtService->generateToken($payload);

        $decoded = $this->jwtService->validateToken($token);

        $this->assertEquals(1, $decoded->id);
        $this->assertEquals('user@example.com', $decoded->email);
    }

    public function testInvalidTokenThrowsException(): void
    {

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token invalide ou expiré.');

        $this->jwtService->validateToken('invalid.token.jwt');
    }

    public function testRefreshToken(): void
    {
        $now = time();
        $token = JWT::encode([
            'id' => 10,
            'email' => 'soon@expire.com',
            'iat' => $now - 55,
            'exp' => $now + 5,
        ], $this->secretKey, 'HS256');

        $newToken = $this->jwtService->refreshToken($token, 10);
        $this->assertIsString($newToken);
        $this->assertNotEquals($token, $newToken);

        $decoded = $this->jwtService->validateToken($newToken);
        $this->assertEquals(10, $decoded->id);
    }

    public function testRefreshTokenThrowsIfNotCloseToExpiration(): void
    {

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage('Le token est encore valide, pas besoin de le rafraîchir.');

        $now = time();
        $token = JWT::encode([
            'id' => 42,
            'email' => 'valid@user.com',
            'iat' => $now,
            'exp' => $now + 1000,
        ], $this->secretKey, 'HS256');

        $this->jwtService->refreshToken($token);
    }

    /**
     * @throws Exception
     */
    public function testTokenIsBlacklisted(): void
    {
        $mockBlacklistService = $this->createMock(BlackListedTokenService::class);
        $mockBlacklistService->method('isBlacklisted')->with('blacklisted.token')->willReturn(true);

        $jwtService = new JwtService(
            $this->secretKey,
            $this->tokenTtl,
            'HS256',
            $mockBlacklistService
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token révoqué.');

        $jwtService->validateToken('blacklisted.token');
        $this->jwtService->refreshToken($token, 300);
    }
}
