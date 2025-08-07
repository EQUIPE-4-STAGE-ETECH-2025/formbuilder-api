<?php

namespace App\Service;

use App\Dto\BlackListedTokenDto;
use App\Dto\LoginDto;
use App\Dto\UserResponseDto;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtService $jwtService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function login(LoginDto $dto): array
    {
        $user = $this->userRepository->findOneBy(['email' => $dto->getEmail()]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $dto->getPassword())) {
            throw new UnauthorizedHttpException('', 'Identifiants invalides.');
        }

        $payload = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
        ];

        $token = $this->jwtService->generateToken($payload);

        $createdAt = $user->getCreatedAt();

        return [
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'isEmailVerified' => $user->isEmailVerified(),
                'role' => $user->getRole(),
                'createdAt' => $createdAt?->format('Y-m-d H:i:s'),
            ],
        ];
    }

    public function getCurrentUser(string $jwt): UserResponseDto
    {
        $payload = $this->jwtService->validateToken($jwt);

        $user = $this->userRepository->find($payload->id ?? null);
        if (!$user) {
            throw new UnauthorizedHttpException('', 'Utilisateur introuvable.');
        }

        return new UserResponseDto($user);
    }

    public function logout(string $jwt): void
    {
        $payload = $this->jwtService->validateToken($jwt);

        if (!isset($payload->exp)) {
            throw new \RuntimeException('Token invalide : propriété exp manquante');
        }

        $dto = new BlackListedTokenDto(
            token: $jwt,
            expiresAt: (new \DateTimeImmutable())->setTimestamp($payload->exp)
        );

        $this->jwtService->blacklistToken($dto);
    }
}
