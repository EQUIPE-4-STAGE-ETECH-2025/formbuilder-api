<?php

namespace App\Tests\Service;

use App\Entity\QuotaStatus;
use App\Entity\User;
use App\Service\EmailService;
use App\Service\QuotaNotificationService;
use App\Service\QuotaStatusService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class QuotaNotificationServiceTest extends TestCase
{
    private QuotaNotificationService $quotaNotificationService;
    private $emailService;
    private $quotaStatusService;
    private $logger;

    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->quotaStatusService = $this->createMock(QuotaStatusService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->quotaNotificationService = new QuotaNotificationService(
            $this->emailService,
            $this->quotaStatusService,
            $this->logger
        );
    }

    public function testCheckAndSendNotificationsWhenNewQuotaStatus(): void
    {
        $user = $this->createUser();
        $quotaData = $this->createQuotaData(10.0, 20.0, 30.0);
        $quotaStatus = $this->createQuotaStatus($user, false, false);

        $this->quotaStatusService
            ->expects($this->once())
            ->method('getOrCreateQuotaStatus')
            ->willReturn($quotaStatus);

        $this->emailService
            ->expects($this->never())
            ->method('sendEmail');

        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);

        $this->assertFalse($quotaStatus->isNotified80());
        $this->assertFalse($quotaStatus->isNotified100());
    }

    public function testCheckAndSendNotificationsWhenNoQuotaStatus(): void
    {
        $user = $this->createUser();
        // 🔥 Corrigé : on met des valeurs < 80% pour ne PAS déclencher d'email
        $quotaData = $this->createQuotaData(50.0, 40.0, 30.0);

        $quotaStatus = $this->createQuotaStatus($user, false, false);

        $this->quotaStatusService
            ->expects($this->once())
            ->method('getOrCreateQuotaStatus')
            ->with($user, $this->isInstanceOf(\DateTimeInterface::class))
            ->willReturn($quotaStatus);

        $this->emailService
            ->expects($this->never())
            ->method('sendEmail');

        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);

        $this->assertFalse($quotaStatus->isNotified80());
        $this->assertFalse($quotaStatus->isNotified100());
    }

    public function testCheckAndSendNotificationsAt80Percent(): void
    {
        $user = $this->createUser();
        $quotaData = $this->createQuotaData(85.0, 90.0, 50.0);
        $quotaStatus = $this->createQuotaStatus($user, false, false);

        $this->quotaStatusService
            ->expects($this->once())
            ->method('getOrCreateQuotaStatus')
            ->willReturn($quotaStatus);

        $this->quotaStatusService
            ->expects($this->once())
            ->method('updateUsageData')
            ->with($quotaStatus, 5, 250, 50);

        $this->emailService
            ->expects($this->once())
            ->method('sendEmail')
            ->with(
                $user->getEmail(),
                'Alerte quota - 80% atteint - FormBuilder',
                $this->stringContains('Vous avez atteint 80%')
            );

        $this->quotaStatusService
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

        $this->quotaStatusService
            ->expects($this->once())
            ->method('getOrCreateQuotaStatus')
            ->willReturn($quotaStatus);

        $this->quotaStatusService
            ->expects($this->once())
            ->method('updateUsageData')
            ->with($quotaStatus, 5, 250, 50);

        $this->emailService
            ->expects($this->once())
            ->method('sendEmail')
            ->with(
                $user->getEmail(),
                'Alerte quota - 100% atteint - FormBuilder',
                $this->stringContains('Vous avez atteint 100%')
            );

        $this->quotaStatusService
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

        $this->quotaStatusService
            ->expects($this->once())
            ->method('getOrCreateQuotaStatus')
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

        $this->quotaStatusService
            ->expects($this->once())
            ->method('getOrCreateQuotaStatus')
            ->willReturn($quotaStatus);

        $this->emailService
            ->expects($this->never())
            ->method('sendEmail');

        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);
    }

    public function testCheckAndSendNotificationsEmailException(): void
    {
        $user = $this->createUser();
        $quotaData = $this->createQuotaData(85.0, 90.0, 50.0);
        $quotaStatus = $this->createQuotaStatus($user, false, false);

        $this->quotaStatusService
            ->expects($this->once())
            ->method('getOrCreateQuotaStatus')
            ->willReturn($quotaStatus);

        $this->quotaStatusService
            ->expects($this->once())
            ->method('updateUsageData')
            ->with($quotaStatus, 5, 250, 50);

        $this->emailService
            ->expects($this->once())
            ->method('sendEmail')
            ->willThrowException(new \Exception('SMTP error'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Erreur envoi notification quota', $this->anything());

        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');

        return $user;
    }

    private function createQuotaStatus(User $user, bool $notified80, bool $notified100): QuotaStatus
    {
        $quotaStatus = new QuotaStatus();
        $quotaStatus->setUser($user);
        $quotaStatus->setNotified80($notified80);
        $quotaStatus->setNotified100($notified100);

        return $quotaStatus;
    }

    private function createQuotaData(float $formsPercent, float $submissionsPercent, float $storagePercent): array
    {
        return [
            'usage' => [
                'form_count' => 5,
                'submission_count' => 250,
                'storage_used_mb' => 50,
            ],
            'limits' => [
                'max_forms' => 10,
                'max_submissions_per_month' => 500,
                'max_storage_mb' => 100,
            ],
            'percentages' => [
                'forms_used_percent' => $formsPercent,
                'submissions_used_percent' => $submissionsPercent,
                'storage_used_percent' => $storagePercent,
            ],
        ];
    }
}
