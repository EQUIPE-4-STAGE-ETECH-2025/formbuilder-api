<?php

namespace App\Tests\Service;

use App\Dto\LoginDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\JwtService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthServiceTest extends TestCase
{
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

        $authService = new AuthService($userRepo, $passwordHasher, $jwtService);

        $result = $authService->login($dto);

        $this->assertEquals('fake-jwt-token', $result['token']);
        $this->assertEquals('test@example.com', $result['user']['email']);
    }

    public function testInvalidLogin(): void
    {
        $dto = new LoginDto();
        $dto->setEmail('wrong@example.com');
        $dto->setPassword('wrong');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $jwtService = $this->createMock(JwtService::class);

        $authService = new AuthService($userRepo, $hasher, $jwtService);

        $this->expectException(UnauthorizedHttpException::class);
        $authService->login($dto);
    }
}
