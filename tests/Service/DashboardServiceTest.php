<?php

namespace App\Tests\Service;

use App\Dto\DashboardStatsDto;
use App\Entity\User;
use App\Repository\FormRepository;
use App\Repository\SubmissionRepository;
use App\Service\DashboardService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    private FormRepository $formRepository;
    private SubmissionRepository $submissionRepository;
    private DashboardService $dashboardService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->formRepository = $this->createMock(FormRepository::class);
        $this->submissionRepository = $this->createMock(SubmissionRepository::class);

        $this->dashboardService = new DashboardService(
            $this->formRepository,
            $this->submissionRepository
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function testGetUserStats(): void
    {
        $user = (new User())->setId('user-123');

        $this->formRepository
            ->method('count')
            ->willReturnMap([
                [['user' => 'user-123'], 5],
                [['user' => 'user-123', 'status' => 'PUBLISHED'], 3],
            ]);

        $this->submissionRepository
            ->method('countByUserForms')
            ->with('user-123')
            ->willReturn(42);

        $this->submissionRepository
            ->method('countSubmissionsPerMonthByUser')
            ->with('user-123')
            ->willReturn(['2025-01' => 10, '2025-02' => 15]);

        $this->submissionRepository
            ->method('countSubmissionsPerFormByUser')
            ->with('user-123')
            ->willReturn(['Form A' => 20, 'Form B' => 22]);

        $this->formRepository
            ->method('countFormsByStatusForUser')
            ->with('user-123')
            ->willReturn(['PUBLISHED' => 3, 'DRAFT' => 2]);

        $dto = $this->dashboardService->getUserStats($user);

        $data = $dto->toArray();

        $this->assertEquals(5, $data['totalForms']);
        $this->assertEquals(3, $data['publishedForms']);
        $this->assertEquals(42, $data['totalSubmissions']);
        $this->assertEquals(['2025-01' => 10, '2025-02' => 15], $data['submissionsPerMonth']);
        $this->assertEquals(['Form A' => 20, 'Form B' => 22], $data['submissionsPerForm']);
        $this->assertEquals(['PUBLISHED' => 3, 'DRAFT' => 2], $data['formsStatusCount']);
    }
}
