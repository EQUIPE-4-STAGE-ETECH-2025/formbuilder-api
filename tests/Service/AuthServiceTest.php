<?php

namespace App\Tests\Service;

use App\Dto\LoginDto;
use App\Dto\RegisterDto;
use App\Dto\ResetPasswordDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\EmailService;
use App\Service\JwtService;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class AuthServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testLoginSuccess() : void {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setRole('USER');

        $dto = new LoginDto();
        $dto->setEmail('test@example.com');
        $dto->setPassword('secret');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')
            ->willReturn($user);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('isPasswordValid')
            ->willReturn(true);

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('generateToken')->willReturn('fake-jwt-token');

        $emailService = $this->createMock(EmailService::class);

        $authService = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService);

        $result = $authService->login($dto);

        $this->assertEquals('fake-jwt-token', $result['token']);
        $this->assertEquals('test@example.com', $result['user']['email']);
    }

    /**
     * @throws Exception
     */
    public function testInvalidLogin(): void
    {
        $dto = new LoginDto();
        $dto->setEmail('wrong@example.com');
        $dto->setPassword('wrong');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $jwtService = $this->createMock(JwtService::class);
        $emailService = $this->createMock(EmailService::class);

        $authService = new AuthService($userRepo, $hasher, $jwtService, $emailService);

        $this->expectException(UnauthorizedHttpException::class);
        $authService->login($dto);
    }

    /**
     * @throws Exception
     */
    public function testGetCurrentUserSuccess(): void
    {
        $user = new User();
        $user->setEmail('me@example.com');

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willReturn((object)['id' => 42]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->with(42)->willReturn($user);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $service = new AuthService($userRepo, $hasher, $jwtService, $emailService);

        $result = $service->getCurrentUser('valid-token');

        $this->assertEquals('me@example.com', $result->getEmail());
    }


    /**
     * @throws Exception
     */
    public function testGetCurrentUserNotFound(): void
    {
        $this->expectException(UnauthorizedHttpException::class);

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willReturn((object)['id' => 42]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->willReturn(null);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $service = new AuthService($userRepo, $hasher, $jwtService, $emailService);

        $service->getCurrentUser('valid-token');
    }

    /**
     * @throws Exception
     */
    public function testLogoutAddsTokenToBlacklist(): void
    {
        $token = 'fake.jwt.token';
        $exp = (new DateTimeImmutable('+1 hour'))->getTimestamp();

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->with($token)->willReturn((object)['exp' => $exp]);
        $jwtService->expects($this->once())->method('blacklistToken');

        $userRepo = $this->createMock(UserRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $service = new AuthService($userRepo, $hasher, $jwtService, $emailService);
        $service->logout($token);
    }

    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testRegisterSuccess(): void
    {
        $_ENV['APP_URL'] = 'http://localhost';

        $dto = new RegisterDto();
        $dto->setFirstName('Alice');
        $dto->setLastName('Wonder');
        $dto->setEmail('alice@example.com');
        $dto->setPassword('MotdepasseFort123!');

        $user = new User();
        $user->setEmail($dto->getEmail());
        $user->setFirstName($dto->getFirstName());
        $user->setLastName($dto->getLastName());
        $user->setRole('USER');
        $user->setIsEmailVerified(false);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);
        $userRepo->method('save')->willReturnCallback(function(User $user, bool $flush) {
            // Simuler ID auto généré
            $user->setId(Uuid::v4());
        });

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('generateToken')->willReturn('fake-token');

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())->method('sendEmailVerification');

        $authService = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService);

        $result = $authService->register($dto);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('fake-token', $result['token']);
    }

    /**
     * @throws Exception
     */
    public function testVerifyEmailSuccess(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail('test@example.com');
        $user->setIsEmailVerified(false);

        $tokenPayload = (object) [
            'id' => $user->getId(),
            'type' => 'email_verification',
        ];

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willReturn($tokenPayload);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->with($user->getId())->willReturn($user);
        $userRepository->expects($this->once())->method('save')->with($user, true);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $authService = new AuthService($userRepository, $passwordHasher, $jwtService, $emailService);

        $authService->verifyEmail('some-token');

        $this->assertTrue($user->isEmailVerified());
    }

    /**
     * @throws Exception
     */
    public function testVerifyEmailThrowsOnInvalidTokenType(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willReturn((object)['type' => 'other_type']);

        $userRepository = $this->createMock(UserRepository::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $authService = new AuthService($userRepository, $passwordHasher, $jwtService, $emailService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token invalide.');

        $authService->verifyEmail('token');
    }

    /**
     * @throws Exception
     */
    public function testVerifyEmailThrowsIfUserNotFound(): void
    {
        $tokenPayload = (object)['id' => 123, 'type' => 'email_verification'];

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willReturn($tokenPayload);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn(null);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $authService = new AuthService($userRepository, $passwordHasher, $jwtService, $emailService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Utilisateur introuvable.');

        $authService->verifyEmail('token');
    }

    /**
     * @throws Exception
     */
    public function testVerifyEmailThrowsIfAlreadyVerified(): void
    {
        $user = new User();
        $user->setIsEmailVerified(true);

        $tokenPayload = (object)['id' => 123, 'type' => 'email_verification'];

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willReturn($tokenPayload);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn($user);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $authService = new AuthService($userRepository, $passwordHasher, $jwtService, $emailService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email déjà vérifié.');

        $authService->verifyEmail('token');
    }

    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testForgotPasswordSendsEmail(): void
    {
        $email = 'user@example.com';
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Jane');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->with(['email' => $email])->willReturn($user);

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->expects($this->once())
            ->method('generateToken')
            ->with($this->callback(fn($payload) =>
                isset($payload['id'], $payload['email'], $payload['type']) &&
                $payload['type'] === 'password_reset' &&
                $payload['email'] === $email
            ))
            ->willReturn('reset-token');

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with($email, 'Jane', 'http://localhost/api/auth/reset-password?token=reset-token');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $_ENV['APP_URL'] = 'http://localhost';

        $service = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService);

        $service->forgotPassword($email);
    }

    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testForgotPasswordThrowsIfUserNotFound(): void
    {
        $email = 'unknown@example.com';

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->with(['email' => $email])->willReturn(null);

        $jwtService = $this->createMock(JwtService::class);
        $emailService = $this->createMock(EmailService::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $service = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Utilisateur inexistant.');

        $service->forgotPassword($email);
    }

    /**
     * @throws Exception
     */
    public function testResetPasswordSuccess(): void
    {
        $token = 'valid-token';
        $newPassword = 'newStrongPass123!';

        $dto = $this->createMock(ResetPasswordDto::class);
        $dto->method('getToken')->willReturn($token);
        $dto->method('getNewPassword')->willReturn($newPassword);

        $user = new User();
        $user->setPasswordHash('oldHash');

        $jwtPayload = (object)[
            'id' => $user->getId(),
            'type' => 'password_reset',
        ];

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->with($user->getId())->willReturn($user);
        $userRepo->expects($this->once())->method('save')->with($user, true);

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->with($token)->willReturn($jwtPayload);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->with($user, $newPassword)->willReturn('hashedNewPassword');

        $emailService = $this->createMock(EmailService::class);

        $service = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService);

        $service->resetPassword($dto);

        $this->assertEquals('hashedNewPassword', $user->getPasswordHash());
        $this->assertInstanceOf(DateTimeImmutable::class, $user->getUpdatedAt());
    }

    /**
     * @throws Exception
     */
    public function testResetPasswordThrowsIfInvalidTokenType(): void
    {
        $token = 'invalid-token';

        $dto = $this->createMock(ResetPasswordDto::class);
        $dto->method('getToken')->willReturn($token);

        $jwtPayload = (object)[
            'id' => 1,
            'type' => 'wrong_type',
        ];

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->with($token)->willReturn($jwtPayload);

        $userRepo = $this->createMock(UserRepository::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $service = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token de réinitialisation invalide.');

        $service->resetPassword($dto);
    }

    /**
     * @throws Exception
     */
    public function testResetPasswordThrowsIfUserNotFound(): void
    {
        $token = 'valid-token';

        $dto = $this->createMock(ResetPasswordDto::class);
        $dto->method('getToken')->willReturn($token);
        $dto->method('getNewPassword')->willReturn('anyPassword');

        $jwtPayload = (object)[
            'id' => 123,
            'type' => 'password_reset',
        ];

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->with($token)->willReturn($jwtPayload);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->with(123)->willReturn(null);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $service = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Utilisateur inexistant.');

        $service->resetPassword($dto);
    }

}
