<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthorizationService;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserServiceTest extends TestCase
{
    private $userRepository;
    private $authorizationService;
    private $userService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->authorizationService = $this->createMock(AuthorizationService::class);

        $this->userService = new UserService($this->userRepository, $this->authorizationService);
    }

    public function testGetUserRoleSuccess(): void
    {
        $userId = '123';
        $user = new User();
        $user->setRole('ADMIN');

        $this->userRepository->expects($this->once())
            ->method('find')
            ->with($userId)
            ->willReturn($user);

        $this->authorizationService->expects($this->once())
            ->method('requirePermissionOnObject')
            ->with('USER_VIEW_ROLE', $user);

        $role = $this->userService->getUserRole($userId);

        $this->assertEquals('ADMIN', $role);
    }

    public function testGetUserRoleUserNotFound(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->userService->getUserRole('non-existent-id');
    }

    public function testUpdateUserRoleSuccess(): void
    {
        $userId = '123';
        $newRole = 'USER';
        $user = new User();
        $user->setRole('ADMIN');

        $this->userRepository->expects($this->once())
            ->method('find')
            ->with($userId)
            ->willReturn($user);

        $this->authorizationService->expects($this->once())
            ->method('requirePermissionOnObject')
            ->with('USER_EDIT_ROLE', $user);

        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($user, true);

        $updatedUser = $this->userService->updateUserRole($userId, $newRole);

        $this->assertEquals($newRole, $updatedUser->getRole());
    }

    public function testUpdateUserRoleUserNotFound(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->userService->updateUserRole('non-existent-id', 'ADMIN');
    }
}
