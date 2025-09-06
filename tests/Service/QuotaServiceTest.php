<?php

namespace App\Tests\Service;

use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\FormRepository;
use App\Repository\SubmissionRepository;
use App\Repository\SubscriptionRepository;
use App\Service\QuotaNotificationService;
use App\Service\QuotaService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

class QuotaServiceTest extends TestCase
{
    private QuotaService $quotaService;
    private $subscriptionRepository;
    private $formRepository;
    private $submissionRepository;
    private $quotaNotificationService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->formRepository = $this->createMock(FormRepository::class);
        $this->submissionRepository = $this->createMock(SubmissionRepository::class);
        $this->quotaNotificationService = $this->createMock(QuotaNotificationService::class);

        $this->quotaService = new QuotaService(
            $this->subscriptionRepository,
            $this->formRepository,
            $this->submissionRepository,
            $this->quotaNotificationService
        );
    }

    public function testCalculateCurrentQuotasSuccess(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $subscription = $this->createSubscription($user, $plan);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn($subscription);

        $this->formRepository
            ->expects($this->once())
            ->method('countByUser')
            ->with($user)
            ->willReturn(5);

        $this->submissionRepository
            ->expects($this->once())
            ->method('countByUserForMonth')
            ->with($user, $this->isInstanceOf(\DateTime::class))
            ->willReturn(250);

        $this->quotaNotificationService
            ->expects($this->once())
            ->method('checkAndSendNotifications')
            ->with($user, $this->isType('array'));

        $result = $this->quotaService->calculateCurrentQuotas($user);

        $this->assertIsArray($result);
        $this->assertEquals($user->getId(), $result['user_id']);
        $this->assertEquals(10, $result['limits']['max_forms']);
        $this->assertEquals(1000, $result['limits']['max_submissions_per_month']);
        $this->assertEquals(100, $result['limits']['max_storage_mb']);
        $this->assertEquals(5, $result['usage']['form_count']);
        $this->assertEquals(250, $result['usage']['submission_count']);
        $this->assertEquals(0, $result['usage']['storage_used_mb']);
        $this->assertEquals(50.0, $result['percentages']['forms_used_percent']);
        $this->assertEquals(25.0, $result['percentages']['submissions_used_percent']);
        $this->assertEquals(0.0, $result['percentages']['storage_used_percent']);
        $this->assertFalse($result['is_over_limit']['forms']);
        $this->assertFalse($result['is_over_limit']['submissions']);
        $this->assertFalse($result['is_over_limit']['storage']);
    }

    public function testCalculateCurrentQuotasThrowsExceptionWhenNoActivePlan(): void
    {
        $user = $this->createUser();

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aucun plan actif trouvé pour l\'utilisateur');

        $this->quotaService->calculateCurrentQuotas($user);
    }

    public function testCanPerformActionCreateFormAllowed(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $subscription = $this->createSubscription($user, $plan);

        $this->setupMocksForQuotaCalculation($user, $subscription, 5, 250);

        $result = $this->quotaService->canPerformAction($user, 'create_form');

        $this->assertTrue($result);
    }

    public function testCanPerformActionCreateFormDenied(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $subscription = $this->createSubscription($user, $plan);

        $this->setupMocksForQuotaCalculation($user, $subscription, 10, 250);

        $result = $this->quotaService->canPerformAction($user, 'create_form');

        $this->assertFalse($result);
    }

    public function testCanPerformActionSubmitFormAllowed(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $subscription = $this->createSubscription($user, $plan);

        $this->setupMocksForQuotaCalculation($user, $subscription, 5, 500);

        $result = $this->quotaService->canPerformAction($user, 'submit_form', 100);

        $this->assertTrue($result);
    }

    public function testCanPerformActionSubmitFormDenied(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $subscription = $this->createSubscription($user, $plan);

        $this->setupMocksForQuotaCalculation($user, $subscription, 5, 950);

        $result = $this->quotaService->canPerformAction($user, 'submit_form', 100);

        $this->assertFalse($result);
    }

    public function testEnforceQuotaLimitSuccess(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $subscription = $this->createSubscription($user, $plan);

        $this->setupMocksForQuotaCalculation($user, $subscription, 5, 250);

        // Ne devrait pas lever d'exception
        $this->quotaService->enforceQuotaLimit($user, 'create_form');
        $this->addToAssertionCount(1); // Indique qu'on teste qu'aucune exception n'est levée
    }

    public function testEnforceQuotaLimitThrowsExceptionForCreateForm(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $subscription = $this->createSubscription($user, $plan);

        $this->setupMocksForQuotaCalculation($user, $subscription, 10, 250);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Limite de formulaires atteinte (10/10)');

        $this->quotaService->enforceQuotaLimit($user, 'create_form');
    }

    public function testEnforceQuotaLimitThrowsExceptionForSubmitForm(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $subscription = $this->createSubscription($user, $plan);

        $this->setupMocksForQuotaCalculation($user, $subscription, 5, 1000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Limite de soumissions atteinte (1000/1000)');

        $this->quotaService->enforceQuotaLimit($user, 'submit_form');
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

    private function createPlan(): Plan
    {
        $plan = new Plan();
        $plan->setId(Uuid::v4());
        $plan->setName('Plan Test');
        $plan->setPriceCents(999);
        $plan->setStripeProductId('prod_test');
        $plan->setMaxForms(10);
        $plan->setMaxSubmissionsPerMonth(1000);
        $plan->setMaxStorageMb(100);

        return $plan;
    }

    private function createSubscription(User $user, Plan $plan): Subscription
    {
        $subscription = new Subscription();
        $subscription->setId(Uuid::v4());
        $subscription->setUser($user);
        $subscription->setPlan($plan);
        $subscription->setStripeSubscriptionId('sub_test');
        $subscription->setStartDate(new \DateTime('2024-01-01'));
        $subscription->setEndDate(new \DateTime('2024-12-31'));
        $subscription->setStatus(Subscription::STATUS_ACTIVE);

        return $subscription;
    }

    private function setupMocksForQuotaCalculation(User $user, Subscription $subscription, int $formCount, int $submissionCount): void
    {
        $this->subscriptionRepository
            ->method('findOneBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn($subscription);

        $this->formRepository
            ->method('countByUser')
            ->with($user)
            ->willReturn($formCount);

        $this->submissionRepository
            ->method('countByUserForMonth')
            ->with($user, $this->isInstanceOf(\DateTime::class))
            ->willReturn($submissionCount);

        $this->quotaNotificationService
            ->method('checkAndSendNotifications');
    }
}
