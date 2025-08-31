<?php

namespace App\Service;

use App\Dto\BlackListedTokenDto;
use App\Dto\ChangePasswordDto;
use App\Dto\LoginDto;
use App\Dto\RegisterDto;
use App\Dto\ResetPasswordDto;
use App\Dto\UserResponseDto;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtService $jwtService,
        private readonly EmailService $emailService
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws TransportExceptionInterface
     */
    public function register(RegisterDto $dto): array
    {
        if ($this->userRepository->findOneBy(['email' => $dto->getEmail()])) {
            throw new RuntimeException('Cet email est déjà utilisé.');
        }

        $user = new User();
        $user->setFirstName($dto->getFirstName() ?? '');
        $user->setLastName($dto->getLastName() ?? '');
        $user->setEmail($dto->getEmail() ?? '');
        $user->setIsEmailVerified(false);
        $user->setRole('USER');

        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->getPassword() ?? '');
        $user->setPasswordHash($hashedPassword);

        $this->userRepository->save($user, true);

        $verificationToken = $this->jwtService->generateToken([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'type' => 'email_verification',
        ]);

        $verificationUrl = sprintf(
            '%s/verify-email?token=%s&email=%s',
            $_ENV['FRONTEND_URL'],
            $verificationToken,
            urlencode($user->getEmail())
        );

        $this->emailService->sendEmailVerification(
            $user->getEmail() ?? '',
            $user->getFirstName() ?? '',
            $verificationUrl
        );

        return [
            'user' => new UserResponseDto($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function login(LoginDto $dto): array
    {
        $user = $this->userRepository->findOneBy(['email' => $dto->getEmail()]);

        if (! $user || ! $this->passwordHasher->isPasswordValid($user, $dto->getPassword())) {
            throw new UnauthorizedHttpException('', 'Identifiants invalides.');
        }

        if (! $user->isEmailVerified()){
            throw new UnauthorizedHttpException('', 'Email non vérifié.');
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
        if (! $user) {
            throw new UnauthorizedHttpException('', 'Utilisateur introuvable.');
        }

        return new UserResponseDto($user);
    }

    public function logout(string $jwt): void
    {
        $payload = $this->jwtService->validateToken($jwt);

        if (! isset($payload->exp)) {
            throw new RuntimeException('Token invalide : propriété exp manquante');
        }

        $dto = new BlackListedTokenDto(
            token: $jwt,
            expiresAt: (new DateTimeImmutable())->setTimestamp($payload->exp)
        );

        $this->jwtService->blacklistToken($dto);
    }

    public function verifyEmail(string $token): void
    {
        try {
            $payload = $this->jwtService->validateToken($token);

            $user = $this->userRepository->find($payload->id ?? null);
            if (! $user) {
                throw new RuntimeException('Utilisateur introuvable.');
            }

            if (! $user->isEmailVerified() && ($payload->type ?? null) === 'email_verification') {
                $user->setIsEmailVerified(true);
                $this->userRepository->save($user, true);
            }

            $this->jwtService->blacklistToken(new BlackListedTokenDto(
                token: $token,
                expiresAt: (new DateTimeImmutable())->setTimestamp($payload->exp)
            ));
        } catch (\Exception $e) {
            throw new RuntimeException('Lien invalide ou expiré.');
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function resendEmailVerification(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (! $user) {
            throw new RuntimeException('Utilisateur inexistant.');
        }

        if ($user->isEmailVerified()) {
            throw new RuntimeException('Email déjà vérifié.');
        }

        $verificationToken = $this->jwtService->generateToken([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'type' => 'email_verification',
        ]);

        $verificationUrl = sprintf(
            '%s/verify-email?token=%s&email=%s',
            $_ENV['FRONTEND_URL'],
            $verificationToken,
            urlencode($user->getEmail())
        );

        $this->emailService->sendEmailVerification(
            $user->getEmail(),
            $user->getFirstName() ?? '',
            $verificationUrl
        );
    }


    /**
     * @throws TransportExceptionInterface
     */
    public function forgotPassword(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (! $user) {
            throw new RuntimeException('Utilisateur inexistant.');
        }

        $resetToken = $this->jwtService->generateToken([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'type' => 'password_reset',
        ]);

        $resetUrl = sprintf(
            '%s/reset-password?token=%s&email=%s',
            $_ENV['FRONTEND_URL'],
            $resetToken,
            urlencode($user->getEmail())
        );

        $this->emailService->sendPasswordResetEmail(
            $user->getEmail() ?? '',
            $user->getFirstName() ?? '',
            $resetUrl
        );
    }

    public function resetPassword(ResetPasswordDto $dto): void
    {
        $token = $dto->getToken();
        if ($token === null) {
            throw new RuntimeException('Token de réinitialisation manquant.');
        }

        $payload = $this->jwtService->validateToken($token);

        if (! isset($payload->type) || $payload->type !== 'password_reset') {
            throw new RuntimeException('Token de réinitialisation invalide.');
        }

        $userId = $payload->id ?? null;
        if ($userId === null) {
            throw new RuntimeException('Token invalide : ID utilisateur manquant.');
        }

        $user = $this->userRepository->find($userId);

        if (! $user) {
            throw new RuntimeException('Utilisateur inexistant.');
        }

        $newPassword = $dto->getNewPassword();
        if ($newPassword === null) {
            throw new RuntimeException('Nouveau mot de passe manquant.');
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPasswordHash($hashedPassword);

        $user->setUpdatedAt(new DateTimeImmutable());

        $this->userRepository->save($user, true);

        if (isset($payload->exp)) {
        $this->jwtService->blacklistToken(new BlackListedTokenDto(
            token: $token,
            expiresAt: (new DateTimeImmutable())->setTimestamp($payload->exp)
        ));
    }
    }

    public function changePassword(string $token, ChangePasswordDto $dto): void
    {
        $payload = $this->jwtService->validateToken($token);
        $user = $this->userRepository->find($payload->id ?? null);
        if (!$user) {
            throw new RuntimeException('Utilisateur inexistant.');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $dto->getCurrentPassword() ?? '')) {
            throw new RuntimeException('Mot de passe actuel invalide.');
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->getNewPassword() ?? '');
        $user->setPasswordHash($hashedPassword);
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->userRepository->save($user, true);
    }
}
