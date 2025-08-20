<?php

namespace App\Tests\Service;

use App\Entity\QuotaStatus;
use App\Entity\User;
use App\Repository\QuotaStatusRepository;
use App\Service\EmailService;
use App\Service\QuotaNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class QuotaNotificationServiceTest extends TestCase
{
    private QuotaNotificationService $quotaNotificationService;
    private $emailService;
    private $quotaStatusRepository;
    private $entityManager;
    private $logger;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->quotaStatusRepository = $this->createMock(QuotaStatusRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->quotaNotificationService = new QuotaNotificationService(
            $this->emailService,
            $this->quotaStatusRepository,
            $this->entityManager,
            $this->logger
        );
    }

    public function testCheckAndSendNotificationsWhenNoQuotaStatus(): void
    {
        $user = $this->createUser();
        $quotaData = $this->createQuotaData(85.0, 90.0, 50.0);

        $this->quotaStatusRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->emailService
            ->expects($this->never())
            ->method('sendEmail');

        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);
    }

    public function testCheckAndSendNotificationsAt80Percent(): void
    {
        $user = $this->createUser();
        $quotaData = $this->createQuotaData(85.0, 90.0, 50.0);
        $quotaStatus = $this->createQuotaStatus($user, false, false);

        $this->quotaStatusRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($quotaStatus);

        $this->emailService
            ->expects($this->once())
            ->method('sendEmail')
            ->with(
                $user->getEmail(),
                'Alerte quota - 80% atteint - FormBuilder',
                $this->stringContains('Vous avez atteint 80%')
            );

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($quotaStatus);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Notification quota envoyée', $this->arrayHasKey('user_id'));

        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);

        $this->assertTrue($quotaStatus->isNotified80());
    }

    public function testCheckAndSendNotificationsAt100Percent(): void
    {
        $user = $this->createUser();
        $quotaData = $this->createQuotaData(100.0, 95.0, 80.0);
        $quotaStatus = $this->createQuotaStatus($user, true, false);

        $this->quotaStatusRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($quotaStatus);

        $this->emailService
            ->expects($this->once())
            ->method('sendEmail')
            ->with(
                $user->getEmail(),
                'Alerte quota - 100% atteint - FormBuilder',
                $this->stringContains('Vous avez atteint 100%')
            );

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($quotaStatus);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);

        $this->assertTrue($quotaStatus->isNotified100());
    }

    public function testCheckAndSendNotificationsAlreadyNotified80(): void
    {
        $user = $this->createUser();
        $quotaData = $this->createQuotaData(85.0, 90.0, 50.0);
        $quotaStatus = $this->createQuotaStatus($user, true, false);

        $this->quotaStatusRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($quotaStatus);

        $this->emailService
            ->expects($this->never())
            ->method('sendEmail');

        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);
    }

    public function testCheckAndSendNotificationsAlreadyNotified100(): void
    {
        $user = $this->createUser();
        $quotaData = $this->createQuotaData(100.0, 95.0, 80.0);
        $quotaStatus = $this->createQuotaStatus($user, true, true);

        $this->quotaStatusRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($quotaStatus);

        $this->emailService
            ->expects($this->never())
            ->method('sendEmail');

        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);
    }

    public function testCheckAndSendNotificationsBelowThreshold(): void
    {
        $user = $this->createUser();
        $quotaData = $this->createQuotaData(70.0, 60.0, 50.0);
        $quotaStatus = $this->createQuotaStatus($user, false, false);

        $this->quotaStatusRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($quotaStatus);

        $this->emailService
            ->expects($this->never())
            ->method('sendEmail');

        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);
    }

    public function testCheckAndSendNotificationsWithEmailException(): void
    {
        $user = $this->createUser();
        $quotaData = $this->createQuotaData(85.0, 90.0, 50.0);
        $quotaStatus = $this->createQuotaStatus($user, false, false);

        $this->quotaStatusRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($quotaStatus);

        $this->emailService
            ->expects($this->once())
            ->method('sendEmail')
            ->willThrowException(new \Exception('Email service error'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Erreur envoi notification quota', $this->arrayHasKey('error'));

        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);

        // La notification ne doit pas être marquée comme envoyée en cas d'erreur
        $this->assertFalse($quotaStatus->isNotified80());
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setRole('USER');

        return $user;
    }

    private function createQuotaStatus(User $user, bool $notified80, bool $notified100): QuotaStatus
    {
        $quotaStatus = new QuotaStatus();
        $quotaStatus->setId(Uuid::v4());
        $quotaStatus->setUser($user);
        $quotaStatus->setMonth(new \DateTime('first day of this month'));
        $quotaStatus->setFormCount(5);
        $quotaStatus->setSubmissionCount(250);
        $quotaStatus->setStorageUsedMb(50);
        $quotaStatus->setNotified80($notified80);
        $quotaStatus->setNotified100($notified100);

        return $quotaStatus;
    }

    private function createQuotaData(float $formsPercent, float $submissionsPercent, float $storagePercent): array
    {
        return [
            'user_id' => Uuid::v4(),
            'month' => '2024-01-01',
            'limits' => [
                'max_forms' => 10,
                'max_submissions_per_month' => 1000,
                'max_storage_mb' => 100,
            ],
            'usage' => [
                'form_count' => 5,
                'submission_count' => 250,
                'storage_used_mb' => 50,
            ],
            'percentages' => [
                'forms_used_percent' => $formsPercent,
                'submissions_used_percent' => $submissionsPercent,
                'storage_used_percent' => $storagePercent,
            ],
            'is_over_limit' => [
                'forms' => $formsPercent >= 100,
                'submissions' => $submissionsPercent >= 100,
                'storage' => $storagePercent >= 100,
            ],
        ];
    }
}
