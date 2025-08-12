<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthorizationService;
use App\Service\UserService;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserServiceTest extends TestCase
{
    private $userRepository;
    private $authorizationService;
    private $validator;
    private $userService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->authorizationService = $this->createMock(AuthorizationService::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->userService = new UserService($this->userRepository, $this->authorizationService, $this->validator);
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

    public function testGetUserProfileSuccess(): void
    {
        $userId = '456';
        $user = new User();

        $this->userRepository->method('find')->with($userId)->willReturn($user);

        $this->authorizationService->expects($this->once())
            ->method('requirePermissionOnObject')
            ->with('USER_VIEW_PROFILE', $user);

        $result = $this->userService->getUserProfile($userId);

        $this->assertSame($user, $result);
    }

    public function testGetUserProfileUserNotFound(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->userService->getUserProfile('not-exist');
    }

    public function testUpdateUserProfileSuccess(): void
    {
        $userId = '789';
        $user = new User();
        $user->setFirstName('Old');
        $user->setLastName('Name');

        $this->userRepository->method('find')->with($userId)->willReturn($user);

        $this->authorizationService->expects($this->once())
            ->method('requirePermissionOnObject')
            ->with('USER_EDIT_PROFILE', $user);

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($user, true);

        $updated = $this->userService->updateUserProfile($userId, [
            'firstName' => 'John',
            'lastName' => 'Doe',
        ]);

        $this->assertEquals('John', $updated->getFirstName());
        $this->assertEquals('Doe', $updated->getLastName());
        $this->assertInstanceOf(DateTimeImmutable::class, $updated->getUpdatedAt());
    }

    public function testUpdateUserProfileValidationError(): void
    {
        $userId = '789';
        $user = new User();

        $this->userRepository->method('find')->with($userId)->willReturn($user);

        $this->authorizationService->method('requirePermissionOnObject');

        $violations = new ConstraintViolationList([
            new ConstraintViolation('Invalid name', null, [], null, 'firstName', null),
        ]);

        $this->validator->method('validate')->willReturn($violations);

        $this->expectException(InvalidArgumentException::class);

        $this->userService->updateUserProfile($userId, ['firstName' => '']);
    }
    public function testListUsersSuccess(): void
    {
        $users = [new User(), new User()];

        $this->authorizationService->method('isGranted')->with('USER_VIEW_ALL')->willReturn(true);
        $this->userRepository->method('findAll')->willReturn($users);

        $result = $this->userService->listUsers();

        $this->assertSame($users, $result);
    }

    public function testListUsersAccessDenied(): void
    {
        $this->authorizationService->method('isGranted')->with('USER_VIEW_ALL')->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);

        $this->userService->listUsers();
    }

    public function testListUsersSuccess(): void
    {
        $users = [new User(), new User()];

        $this->authorizationService->method('isGranted')->with('USER_VIEW_ALL')->willReturn(true);
        $this->userRepository->method('findAll')->willReturn($users);

        $result = $this->userService->listUsers();

        $this->assertSame($users, $result);
    }

    public function testListUsersAccessDenied(): void
    {
        $this->authorizationService->method('isGranted')->with('USER_VIEW_ALL')->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);

        $this->userService->listUsers();
    }

    public function testDeleteUserSuccess(): void
    {
        $userId = '111';
        $user = new User();

        $this->userRepository->method('find')->with($userId)->willReturn($user);

        $this->authorizationService->expects($this->once())
            ->method('requirePermissionOnObject')
            ->with('USER_DELETE', $user);

        $this->userRepository->expects($this->once())
            ->method('remove')
            ->with($user, true);

        $this->userService->deleteUser($userId);
    }

    public function testDeleteUserNotFound(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->userService->deleteUser('missing');
    }
}