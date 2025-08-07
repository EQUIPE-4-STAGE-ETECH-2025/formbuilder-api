<?php

namespace App\Service;

use App\Dto\BlackListedTokenDto;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secretKey;
    private string $algorithm;
    private int $tokenTtl; // durée de vie du token
    private ?BlackListedTokenService $blacklistService;

    public function __construct(string $secretKey, int $tokenTtl = 3600, string $algorithm = 'HS256', ?BlackListedTokenService $blacklistService = null)
    {
        $this->secretKey = $secretKey;
        $this->tokenTtl = $tokenTtl;
        $this->algorithm = $algorithm;
        $this->blacklistService = $blacklistService;
    }

    // Génère un token JWT pour les données utilisateur passées en payload.
    /**
     * @param array<string, mixed> $payload
     */
    public function generateToken(array $payload): string
    {
        $issuedAt = new \DateTimeImmutable();
        $expire = $issuedAt->modify("+{$this->tokenTtl} seconds");

        $tokenPayload = array_merge($payload, [
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expire->getTimestamp(),
        ]);

        return JWT::encode($tokenPayload, $this->secretKey, $this->algorithm);
    }

    // Valide un token JWT et retourne le payload décodé.
    public function validateToken(string $token): object
    {
        if ($this->blacklistService && $this->blacklistService->isBlacklisted($token)) {
            throw new \RuntimeException('Token révoqué.');
        }

        try {
            return JWT::decode($token, new Key($this->secretKey, $this->algorithm));
        } catch (\Exception $e) {
            throw new \RuntimeException('Token invalide ou expiré.');
        }
    }

    // Refresh un token
    public function refreshToken(string $token, int $refreshThresholdSeconds = 300): string
    {
        $decoded = $this->validateToken($token);

        if (!isset($decoded->exp)) {
            throw new \RuntimeException('Token invalide : propriété exp manquante');
        }

        $now = time();
        $timeLeft = $decoded->exp - $now;

        if ($timeLeft > $refreshThresholdSeconds) {
            throw new \RuntimeException('Le token est encore valide, pas besoin de le rafraîchir.');
        }

        $payload = (array) $decoded;
        unset($payload['iat'], $payload['exp']);

        return $this->generateToken($payload);
    }

    // Révoque un token
    public function blacklistToken(BlackListedTokenDto $token): void
    {
        if (!$this->blacklistService) {
            throw new \RuntimeException('Blacklist service non configuré.');
        }

        $this->blacklistService->blacklist($token);
    }
}
