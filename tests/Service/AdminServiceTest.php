<?php

namespace App\Tests\Service;

use App\Dto\AdminStatsDto;
use App\Dto\UserListDto;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\FormRepository;
use App\Repository\SubmissionRepository;
use App\Repository\UserRepository;
use App\Service\AdminService;
use App\Service\AuthorizationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminServiceTest extends TestCase
{
    private $userRepository;
    private $formRepository;
    private $submissionRepository;
    private $auditLogRepository;
    private $authorizationService;
    private $adminService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->formRepository = $this->createMock(FormRepository::class);
        $this->submissionRepository = $this->createMock(SubmissionRepository::class);
        $this->auditLogRepository = $this->createMock(AuditLogRepository::class);
        $this->authorizationService = $this->createMock(AuthorizationService::class);

        $this->adminService = new AdminService(
            $this->userRepository,
            $this->formRepository,
            $this->submissionRepository,
            $this->auditLogRepository,
            $this->authorizationService
        );
    }

    public function testListUsersThrowsIfNotAuthorized(): void
    {
        $this->authorizationService
            ->method('isGranted')
            ->with('USER_VIEW_ALL')
            ->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);

        $this->adminService->listUsers();
    }

    public function testListUsersReturnsDtos(): void
    {
        $this->authorizationService
            ->method('isGranted')
            ->willReturn(true);

        $user1 = (new User())->setId(1)->setRole('USER')->setEmail('user1@test.com');
        $user2 = (new User())->setId(2)->setRole('ADMIN')->setEmail('admin@test.com'); // doit être exclu

        $this->userRepository
            ->method('findAll')
            ->willReturn([$user1, $user2]);

        $this->formRepository
            ->method('count')
            ->with(['user' => 1])
            ->willReturn(5);

        $this->submissionRepository
            ->method('countByUserForms')
            ->with(1)
            ->willReturn(10);

        $this->userRepository
            ->method('getPlanNameForUser')
            ->with($user1)
            ->willReturn('Pro');

        $result = $this->adminService->listUsers();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(UserListDto::class, $result[0]);

        $data = $result[0]->toArray();
        $this->assertSame('Pro', $data['planName']);
        $this->assertSame(5, $data['formsCount']);
        $this->assertSame(10, $data['submissionsCount']);
    }

    public function testGetStatsReturnsDto(): void
    {
        $this->authorizationService
            ->method('isGranted')
            ->willReturn(true);

        $this->userRepository->method('countNonAdminUsers')->willReturn(3);
        $this->formRepository->method('countAllForms')->willReturn(7);
        $this->submissionRepository->method('countAllSubmissions')->willReturn(15);

        $this->userRepository
            ->method('getUsersPerMonth')
            ->willReturn([
                ['month' => '2025-01', 'count' => 1],
                ['month' => '2025-02', 'count' => 2],
            ]);

        $this->userRepository
            ->method('getUsersByPlan')
            ->willReturn([
                ['plan' => 'Free', 'count' => 2],
                ['plan' => 'Pro', 'count' => 1],
            ]);

        $this->auditLogRepository
            ->method('getRecentAdminActions')
            ->willReturn([['action' => 'login']]);

        $result = $this->adminService->getStats();

        $this->assertInstanceOf(AdminStatsDto::class, $result);
        $this->assertSame(3, $result->totalUsers);
        $this->assertSame(7, $result->totalForms);
        $this->assertSame(15, $result->totalSubmissions);

        // Vérifie le cumul
        $this->assertSame([
            ['month' => '2025-01', 'count' => 1],
            ['month' => '2025-02', 'count' => 3], // cumul
        ], $result->totalUsersPerMonth);

        $this->assertSame([['action' => 'login']], $result->recentAuditLogs);
    }

    public function testGetStatsThrowsIfNotAuthorized(): void
    {
        $this->authorizationService
            ->method('isGranted')
            ->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);

        $this->adminService->getStats();
    }
}
