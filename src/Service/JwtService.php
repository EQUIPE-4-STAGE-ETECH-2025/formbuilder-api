<?php

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DateTimeImmutable;
use Exception;

class JwtService
{
    private string $secretKey;
    private string $algorithm;
    private int $tokenTtl; // durée de vie du token

    public function __construct(string $secretKey, int $tokenTtl = 3600, string $algorithm = 'HS256')
    {
        $this->secretKey = $secretKey;
        $this->tokenTtl = $tokenTtl;
        $this->algorithm = $algorithm;
    }

    //Génère un token JWT pour les données utilisateur passées en payload.
    public function generateToken(array $payload): string
    {
        $issuedAt = new DateTimeImmutable();
        $expire = $issuedAt->modify("+{$this->tokenTtl} seconds");

        $tokenPayload = array_merge($payload, [
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expire->getTimestamp(),
        ]);

        return JWT::encode($tokenPayload, $this->secretKey, $this->algorithm);
    }

    /**
     * Valide un token JWT et retourne le payload décodé.
     * Lance une Exception si invalide ou expiré.
     */
    public function validateToken(string $token): object
    {
        try {
            return JWT::decode($token, new Key($this->secretKey, $this->algorithm));
        } catch (Exception $e) {
            throw new \RuntimeException('Token invalide ou expiré.');
        }
    }

    //Refresh un token : génère un nouveau token si l’ancien est valide et proche de l’expiration.
    public function refreshToken(string $token, int $refreshThresholdSeconds = 300): string
    {
        $decoded = $this->validateToken($token);

        $now = time();
        $timeLeft = $decoded->exp - $now;

        if ($timeLeft > $refreshThresholdSeconds) {
            throw new \RuntimeException('Le token est encore valide, pas besoin de le rafraîchir.');
        }

        // On retire les claims standard pour générer un nouveau token propre
        $payload = (array)$decoded;
        unset($payload['iat'], $payload['exp']);

        return $this->generateToken($payload);
    }
}
