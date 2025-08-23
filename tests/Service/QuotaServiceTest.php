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
        $plan = $this->createFreePlan();
        $subscription = $this->createSubscription($user, $plan);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['user' => $user, 'status' => 'ACTIVE'])
            ->willReturn($subscription);

        $this->formRepository->method('countByUser')->with($user)->willReturn(2);
        $this->submissionRepository->method('countByUserForMonth')->with($user, $this->isInstanceOf(\DateTime::class))->willReturn(100);

        $this->quotaNotificationService->expects($this->once())->method('checkAndSendNotifications');

        $result = $this->quotaService->calculateCurrentQuotas($user);

        $this->assertEquals(3, $result['limits']['max_forms']);
        $this->assertEquals(500, $result['limits']['max_submissions_per_month']);
        $this->assertEquals(50, $result['limits']['max_storage_mb']);
        $this->assertEquals(2, $result['usage']['form_count']);
        $this->assertEquals(100, $result['usage']['submission_count']);
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

    private function createFreePlan(): Plan
    {
        $plan = new Plan();
        $plan->setId(Uuid::v4());
        $plan->setName('Free');
        $plan->setPriceCents(0);
        $plan->setStripeProductId('prod_free');
        $plan->setMaxForms(3);
        $plan->setMaxSubmissionsPerMonth(500);
        $plan->setMaxStorageMb(50);

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
}
