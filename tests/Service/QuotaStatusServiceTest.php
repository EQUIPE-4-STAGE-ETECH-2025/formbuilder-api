<?php

namespace App\Tests\Service;

use App\Entity\QuotaStatus;
use App\Entity\User;
use App\Repository\QuotaStatusRepository;
use App\Service\QuotaStatusService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class QuotaStatusServiceTest extends TestCase
{
    private QuotaStatusService $quotaStatusService;
    private QuotaStatusRepository $quotaStatusRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->quotaStatusRepository = $this->createMock(QuotaStatusRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->quotaStatusService = new QuotaStatusService(
            $this->quotaStatusRepository,
            $this->entityManager,
            $this->logger
        );
    }

    public function testGetOrCreateQuotaStatusCreatesNewWhenNotExists(): void
    {
        $user = new User();
        $user->setId('user-123');
        $month = new DateTime('2024-01-15'); // Milieu du mois

        $this->quotaStatusRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Nouveau QuotaStatus créé automatiquement');

        $result = $this->quotaStatusService->getOrCreateQuotaStatus($user, $month);

        $this->assertInstanceOf(QuotaStatus::class, $result);
        $this->assertEquals($user, $result->getUser());
        $this->assertEquals('2024-01-01', $result->getMonth()->format('Y-m-d'));
        $this->assertEquals(0, $result->getFormCount());
        $this->assertEquals(0, $result->getSubmissionCount());
        $this->assertEquals(0, $result->getStorageUsedMb());
        $this->assertFalse($result->isNotified80());
        $this->assertFalse($result->isNotified100());
    }

    public function testGetOrCreateQuotaStatusReturnsExistingWhenExists(): void
    {
        $user = new User();
        $existingQuotaStatus = new QuotaStatus();
        $month = new DateTime('2024-01-01');

        $this->quotaStatusRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingQuotaStatus);

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->logger
            ->expects($this->never())
            ->method('info');

        $result = $this->quotaStatusService->getOrCreateQuotaStatus($user, $month);

        $this->assertSame($existingQuotaStatus, $result);
    }

    public function testUpdateUsageData(): void
    {
        $quotaStatus = new QuotaStatus();

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($quotaStatus);

        $this->quotaStatusService->updateUsageData($quotaStatus, 5, 100, 25);

        $this->assertEquals(5, $quotaStatus->getFormCount());
        $this->assertEquals(100, $quotaStatus->getSubmissionCount());
        $this->assertEquals(25, $quotaStatus->getStorageUsedMb());
    }
}
