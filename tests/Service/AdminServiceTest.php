<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AdminService;
use App\Service\AuthorizationService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminServiceTest extends TestCase
{
    private $userRepository;
    private $authorizationService;
    private $adminService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->authorizationService = $this->createMock(AuthorizationService::class);

        $this->adminService = new AdminService($this->userRepository, $this->authorizationService);
    }

    public function testListUsersSuccess(): void
    {
        $users = [new User(), new User()];

        $this->authorizationService->method('isGranted')->with('USER_VIEW_ALL')->willReturn(true);
        $this->userRepository->method('findAll')->willReturn($users);

        $result = $this->adminService->listUsers();

        $this->assertSame($users, $result);
    }

    public function testListUsersAccessDenied(): void
    {
        $this->authorizationService->method('isGranted')->with('USER_VIEW_ALL')->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);

        $this->adminService->listUsers();
    }
}
