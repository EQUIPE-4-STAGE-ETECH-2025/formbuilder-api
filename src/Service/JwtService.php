<?php

namespace App\Service;

use App\Dto\BlackListedTokenDto;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secretKey;
    private string $algorithm;
    private int $tokenTtl; // durée de vie du token principal
    private int $refreshTokenTtl; // durée de vie du refresh token
    private ?BlackListedTokenService $blacklistService;

    public function __construct(
        string $secretKey,
        int $tokenTtl = 3600,
        int $refreshTokenTtl = 604800, // 7 jours par défaut
        string $algorithm = 'HS256',
        ?BlackListedTokenService $blacklistService = null
    ) {
        $this->secretKey = $secretKey;
        $this->tokenTtl = $tokenTtl;
        $this->refreshTokenTtl = $refreshTokenTtl;
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

        // Si une expiration personnalisée est fournie, l'utiliser, sinon utiliser la TTL par défaut
        if (! isset($payload['exp'])) {
            $expire = $issuedAt->modify("+{$this->tokenTtl} seconds");
            $payload['exp'] = $expire->getTimestamp();
        }

        $tokenPayload = array_merge($payload, [
            'iat' => $issuedAt->getTimestamp(),
            'type' => $payload['type'] ?? 'access', // Utiliser le type fourni ou 'access' par défaut
        ]);

        return JWT::encode($tokenPayload, $this->secretKey, $this->algorithm);
    }

    // Génère un refresh token avec une durée de vie plus longue
    /**
     * @param array<string, mixed> $payload
     */
    public function generateRefreshToken(array $payload): string
    {
        $issuedAt = new \DateTimeImmutable();
        $expire = $issuedAt->modify("+{$this->refreshTokenTtl} seconds");

        $refreshPayload = array_merge($payload, [
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expire->getTimestamp(),
            'type' => 'refresh', // Type de token
        ]);

        return JWT::encode($refreshPayload, $this->secretKey, $this->algorithm);
    }

    // Valide un token JWT et retourne le payload décodé.
    public function validateToken(string $token): object
    {
        if ($this->blacklistService && $this->blacklistService->isBlacklisted($token)) {
            throw new \RuntimeException('Token révoqué.');
        }

        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));

            // Vérifier le type de token - permettre les tokens spéciaux
            if (isset($decoded->type) && ! in_array($decoded->type, ['access', 'email_verification', 'password_reset'])) {
                throw new \RuntimeException('Type de token invalide. Token d\'accès requis.');
            }

            return $decoded;
        } catch (\Exception $e) {
            throw new \RuntimeException('Token invalide ou expiré.');
        }
    }

    // Valide un refresh token et retourne le payload décodé.
    public function validateRefreshToken(string $refreshToken): object
    {
        if ($this->blacklistService && $this->blacklistService->isBlacklisted($refreshToken)) {
            throw new \RuntimeException('Refresh token révoqué.');
        }

        try {
            $decoded = JWT::decode($refreshToken, new Key($this->secretKey, $this->algorithm));

            // Vérifier le type de token
            if (! isset($decoded->type) || $decoded->type !== 'refresh') {
                throw new \RuntimeException('Type de token invalide. Refresh token requis.');
            }

            return $decoded;
        } catch (\Exception $e) {
            throw new \RuntimeException('Refresh token invalide ou expiré.');
        }
    }

    // Refresh un token en utilisant un refresh token valide
    public function refreshAccessToken(string $refreshToken): string
    {
        $decoded = $this->validateRefreshToken($refreshToken);

        // Extraire les données utilisateur du refresh token
        $payload = (array) $decoded;
        unset($payload['iat'], $payload['exp'], $payload['type']);

        // Générer un nouveau token d'accès
        return $this->generateToken($payload);
    }

    // Refresh un token (méthode existante pour compatibilité)
    public function refreshToken(string $token, int $refreshThresholdSeconds = 300): string
    {
        $decoded = $this->validateToken($token);

        if (! isset($decoded->exp)) {
            throw new \RuntimeException('Token invalide : propriété exp manquante');
        }

        $now = time();
        $timeLeft = $decoded->exp - $now;

        if ($timeLeft > $refreshThresholdSeconds) {
            throw new \RuntimeException('Le token est encore valide, pas besoin de le rafraîchir.');
        }

        $payload = (array) $decoded;
        unset($payload['iat'], $payload['exp'], $payload['type']);

        return $this->generateToken($payload);
    }

    // Révoque un token
    public function blacklistToken(BlackListedTokenDto $token): void
    {
        if (! $this->blacklistService) {
            throw new \RuntimeException('Blacklist service non configuré.');
        }

        $this->blacklistService->blacklist($token);
    }

    // Révoque un refresh token
    public function blacklistRefreshToken(string $refreshToken): void
    {
        $decoded = $this->validateRefreshToken($refreshToken);

        if (! isset($decoded->exp)) {
            throw new \RuntimeException('Refresh token invalide : propriété exp manquante');
        }

        $dto = new BlackListedTokenDto(
            token: $refreshToken,
            expiresAt: (new \DateTimeImmutable())->setTimestamp($decoded->exp)
        );

        $this->blacklistToken($dto);
    }
}
