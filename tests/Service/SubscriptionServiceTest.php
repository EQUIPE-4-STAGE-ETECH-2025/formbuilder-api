<?php

namespace App\Tests\Service;

use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\PlanRepository;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SubscriptionServiceTest extends TestCase
{
    private SubscriptionRepository $subscriptionRepository;
    private PlanRepository $planRepository;
    private LoggerInterface $logger;
    private SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->planRepository = $this->createMock(PlanRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriptionService = new SubscriptionService(
            $this->planRepository,
            $this->subscriptionRepository,
            $this->logger
        );
    }

    public function testHasActiveSubscriptionWithActive(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $subscription = new Subscription();
        $subscription->setStatus(Subscription::STATUS_ACTIVE);

        $this->subscriptionRepository
            ->method('findOneBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn($subscription);

        $result = $this->subscriptionService->hasActiveSubscription($user);

        $this->assertTrue($result);
    }

    public function testHasActiveSubscriptionWithNoActive(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->subscriptionRepository
            ->method('findOneBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn(null);

        $result = $this->subscriptionService->hasActiveSubscription($user);

        $this->assertFalse($result);
    }

    public function testCreateSubscriptionCancelsExistingActive(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $oldPlan = new Plan();
        $oldPlan->setName('Old Plan');

        $newPlan = new Plan();
        $newPlan->setName('New Plan');

        // Abonnement actif existant
        $existingSubscription = new Subscription();
        $existingSubscription->setStatus(Subscription::STATUS_ACTIVE);
        $existingSubscription->setPlan($oldPlan);

        $this->subscriptionRepository
            ->method('findBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn([$existingSubscription]);

        $this->subscriptionRepository
            ->expects($this->exactly(2)) // Une fois pour annuler l'ancien, une fois pour sauver le nouveau
            ->method('save');

        $result = $this->subscriptionService->createSubscription($user, $newPlan);

        // L'ancien doit être annulé
        $this->assertEquals(Subscription::STATUS_CANCELLED, $existingSubscription->getStatus());

        // Le nouveau doit être actif
        $this->assertEquals(Subscription::STATUS_ACTIVE, $result->getStatus());
        $this->assertSame($newPlan, $result->getPlan());
    }
}
