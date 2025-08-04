<?php

namespace App\Tests\Service;

use App\Dto\LoginDto;
use App\Dto\RegisterDto;
use App\Dto\ResetPasswordDto;
use App\Dto\UserResponseDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\EmailService;
use App\Service\JwtService;
use App\Service\SubscriptionService;
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
    public function testLoginSuccess(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setRole('USER');
        $user->setIsEmailVerified(true);

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
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $authService = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService, $subscriptionService);

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
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $authService = new AuthService($userRepo, $hasher, $jwtService, $emailService, $subscriptionService);

        $this->expectException(UnauthorizedHttpException::class);
        $authService->login($dto);
    }

    /**
     * @throws Exception
     */
    public function testLoginThrowsIfEmailNotVerified(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hashed');
        $user->setIsEmailVerified(false);

        $dto = new LoginDto();
        $dto->setEmail('test@example.com');
        $dto->setPassword('secret');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('isPasswordValid')->willReturn(true);

        $jwtService = $this->createMock(JwtService::class);
        $emailService = $this->createMock(EmailService::class);
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $service = new AuthService($userRepo, $hasher, $jwtService, $emailService, $subscriptionService);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Email non vérifié.');

        $service->login($dto);
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
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $service = new AuthService($userRepo, $hasher, $jwtService, $emailService, $subscriptionService);

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
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $service = new AuthService($userRepo, $hasher, $jwtService, $emailService, $subscriptionService);

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
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $service = new AuthService($userRepo, $hasher, $jwtService, $emailService, $subscriptionService);
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
        $userRepo->method('save')->willReturnCallback(function (User $user) {
            // Simuler ID auto généré
            $user->setId(Uuid::v4());
        });

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('generateToken')->willReturn('fake-token');

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())->method('sendEmailVerification');

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService->expects($this->once())->method('assignDefaultFreePlan');

        $authService = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService, $subscriptionService);

        $result = $authService->register($dto);

        $this->assertArrayHasKey('user', $result);
        $this->assertInstanceOf(UserResponseDto::class, $result['user']);

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
            'exp' => (new DateTimeImmutable('+1 hour'))->getTimestamp(),
        ];

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willReturn($tokenPayload);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->with($user->getId())->willReturn($user);
        $userRepository->expects($this->once())->method('save')->with($user, true);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $authService = new AuthService($userRepository, $passwordHasher, $jwtService, $emailService, $subscriptionService);

        $authService->verifyEmail('some-token');

        $this->assertTrue($user->isEmailVerified());
    }

    /**
     * @throws Exception
     */
    public function testVerifyEmailWithInvalidToken(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->never())->method('save');

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willThrowException(new RuntimeException('Token invalide'));
        $jwtService->expects($this->never())->method('blacklistToken');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $authService = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService, $subscriptionService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lien invalide ou expiré.');

        $authService->verifyEmail('invalid-token');
    }

    /**
     * @throws Exception
     */
    public function testVerifyEmailUserNotFound(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->willReturn(null);
        $userRepo->expects($this->never())->method('save');

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willReturn((object)[
            'id' => 123,
            'type' => 'email_verification',
            'exp' => (new DateTimeImmutable('+1 hour'))->getTimestamp(),
        ]);
        $jwtService->expects($this->never())->method('blacklistToken');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $authService = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService, $subscriptionService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lien invalide ou expiré.');

        $authService->verifyEmail('token-user-not-found');
    }

    /**
     * @throws Exception
     */
    public function testVerifyEmailThrowsIfAlreadyVerified(): void
    {
        $user = new User();
        $user->setIsEmailVerified(true);

        $tokenPayload = (object)[
            'id' => 123,
            'type' => 'email_verification',
            'exp' => (new DateTimeImmutable('+1 hour'))->getTimestamp(),
        ];

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('validateToken')->willReturn($tokenPayload);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn($user);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $emailService = $this->createMock(EmailService::class);
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $authService = new AuthService($userRepository, $passwordHasher, $jwtService, $emailService, $subscriptionService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email déjà vérifié.');

        $authService->verifyEmail('token');
    }

    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testResendEmailVerificationSuccess(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail('user@example.com');
        $user->setFirstName('Alice');
        $user->setIsEmailVerified(false);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->with(['email' => 'user@example.com'])->willReturn($user);

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('generateToken')->willReturn('resend-token');

        $emailService = $this->createMock(EmailService::class);
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $emailService->expects($this->once())
            ->method('sendEmailVerification')
            ->with('user@example.com', 'Alice', 'http://localhost:3000/verify-email?token=resend-token&email=user%40example.com');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $service = new AuthService($userRepo, $hasher, $jwtService, $emailService, $subscriptionService);
        $service->resendEmailVerification('user@example.com');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    public function testResendEmailVerificationThrowsIfUserNotFound(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);

        $jwtService = $this->createMock(JwtService::class);
        $emailService = $this->createMock(EmailService::class);
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $service = new AuthService($userRepo, $hasher, $jwtService, $emailService, $subscriptionService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Utilisateur inexistant.');

        $service->resendEmailVerification('notfound@example.com');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    public function testResendEmailVerificationThrowsIfAlreadyVerified(): void
    {
        $user = new User();
        $user->setEmail('verified@example.com');
        $user->setIsEmailVerified(true);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $jwtService = $this->createMock(JwtService::class);
        $emailService = $this->createMock(EmailService::class);
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $service = new AuthService($userRepo, $hasher, $jwtService, $emailService, $subscriptionService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email déjà vérifié.');

        $service->resendEmailVerification('verified@example.com');
    }

    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testForgotPasswordSendsEmail(): void
    {
        $email = 'user@example.com';
        $firstName = 'Jane';
        $token = 'reset-token';
        $expectedUrl = 'http://localhost:3000/reset-password?token=reset-token&email=user%40example.com';

        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setIsEmailVerified(true);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn($user);

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('generateToken')->willReturn($token);

        $emailService = $this->createMock(EmailService::class);
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $emailService->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with($email, $firstName, $expectedUrl);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $authService = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService, $subscriptionService);

        $authService->forgotPassword($email);
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
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $service = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService, $subscriptionService);

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
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $service = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService, $subscriptionService);

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
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $service = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService, $subscriptionService);

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
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $service = new AuthService($userRepo, $passwordHasher, $jwtService, $emailService, $subscriptionService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Utilisateur inexistant.');

        $service->resetPassword($dto);
    }
}