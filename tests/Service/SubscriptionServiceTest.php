<?php

namespace App\Tests\Service;

use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\PlanRepository;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

class SubscriptionServiceTest extends TestCase
{
    private PlanRepository $planRepository;
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionService $subscriptionService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->planRepository = $this->createMock(PlanRepository::class);
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->subscriptionService = new SubscriptionService(
            $this->planRepository,
            $this->subscriptionRepository
        );
    }

    public function testAssignDefaultFreePlanCreatesNewSubscription(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail('test@example.com');

        $freePlan = new Plan();
        $freePlan->setId(Uuid::v4());
        $freePlan->setName('Free');
        $freePlan->setPriceCents(0);

        // L'utilisateur n'a pas de subscription active
        $this->subscriptionRepository
            ->method('findOneBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn(null);

        // Le plan Free existe
        $this->planRepository
            ->method('findDefaultFreePlan')
            ->willReturn($freePlan);

        // La subscription sera sauvegardée
        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Subscription::class), true);

        $result = $this->subscriptionService->assignDefaultFreePlan($user);

        $this->assertInstanceOf(Subscription::class, $result);
        $this->assertEquals($user, $result->getUser());
        $this->assertEquals($freePlan, $result->getPlan());
        $this->assertEquals(Subscription::STATUS_ACTIVE, $result->getStatus());
    }

    public function testAssignDefaultFreePlanReturnsExistingSubscription(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());

        $existingSubscription = new Subscription();
        $existingSubscription->setUser($user);
        $existingSubscription->setStatus(Subscription::STATUS_ACTIVE);

        // L'utilisateur a déjà une subscription active
        $this->subscriptionRepository
            ->method('findOneBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn($existingSubscription);

        // Le plan repository ne devrait pas être appelé
        $this->planRepository
            ->expects($this->never())
            ->method('findDefaultFreePlan');

        // Aucune sauvegarde ne devrait être effectuée
        $this->subscriptionRepository
            ->expects($this->never())
            ->method('save');

        $result = $this->subscriptionService->assignDefaultFreePlan($user);

        $this->assertEquals($existingSubscription, $result);
    }

    public function testAssignDefaultFreePlanThrowsWhenNoFreePlan(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());

        // L'utilisateur n'a pas de subscription active
        $this->subscriptionRepository
            ->method('findOneBy')
            ->willReturn(null);

        // Aucun plan Free trouvé
        $this->planRepository
            ->method('findDefaultFreePlan')
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Plan Free par défaut introuvable. Veuillez contacter le support.');

        $this->subscriptionService->assignDefaultFreePlan($user);
    }

    public function testCreateSubscription(): void
    {
        $user = new User();
        $user->setId(Uuid::v4());

        $plan = new Plan();
        $plan->setId(Uuid::v4());
        $plan->setName('Premium');

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Subscription::class), true);

        $result = $this->subscriptionService->createSubscription($user, $plan);

        $this->assertInstanceOf(Subscription::class, $result);
        $this->assertEquals($user, $result->getUser());
        $this->assertEquals($plan, $result->getPlan());
        $this->assertEquals(Subscription::STATUS_ACTIVE, $result->getStatus());
        $this->assertInstanceOf(\DateTime::class, $result->getStartDate());
        $this->assertInstanceOf(\DateTime::class, $result->getEndDate());
        $this->assertNotEmpty($result->getStripeSubscriptionId());
    }

    public function testHasActiveSubscriptionReturnsTrueWhenActive(): void
    {
        $user = new User();
        $subscription = new Subscription();

        $this->subscriptionRepository
            ->method('findOneBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn($subscription);

        $result = $this->subscriptionService->hasActiveSubscription($user);

        $this->assertTrue($result);
    }

    public function testHasActiveSubscriptionReturnsFalseWhenNoActive(): void
    {
        $user = new User();

        $this->subscriptionRepository
            ->method('findOneBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn(null);

        $result = $this->subscriptionService->hasActiveSubscription($user);

        $this->assertFalse($result);
    }

    public function testGetActiveSubscription(): void
    {
        $user = new User();
        $subscription = new Subscription();

        $this->subscriptionRepository
            ->method('findOneBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn($subscription);

        $result = $this->subscriptionService->getActiveSubscription($user);

        $this->assertEquals($subscription, $result);
    }

    public function testCancelActiveSubscriptions(): void
    {
        $user = new User();

        $subscription1 = new Subscription();
        $subscription1->setStatus(Subscription::STATUS_ACTIVE);

        $subscription2 = new Subscription();
        $subscription2->setStatus(Subscription::STATUS_ACTIVE);

        $activeSubscriptions = [$subscription1, $subscription2];

        $this->subscriptionRepository
            ->method('findBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn($activeSubscriptions);

        $this->subscriptionRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->isInstanceOf(Subscription::class), false);

        // Mock la méthode flush du repository directement
        $this->subscriptionRepository
            ->expects($this->once())
            ->method('flush');

        $this->subscriptionService->cancelActiveSubscriptions($user);

        $this->assertEquals(Subscription::STATUS_CANCELLED, $subscription1->getStatus());
        $this->assertEquals(Subscription::STATUS_CANCELLED, $subscription2->getStatus());
    }

    public function testChangePlan(): void
    {
        $user = new User();

        $oldSubscription = new Subscription();
        $oldSubscription->setStatus(Subscription::STATUS_ACTIVE);

        $newPlan = new Plan();
        $newPlan->setId(Uuid::v4());
        $newPlan->setName('Premium');

        // Mock pour cancelActiveSubscriptions
        $this->subscriptionRepository
            ->method('findBy')
            ->with(['user' => $user, 'status' => Subscription::STATUS_ACTIVE])
            ->willReturn([$oldSubscription]);

        // Mock la méthode flush du repository directement
        $this->subscriptionRepository
            ->expects($this->once())
            ->method('flush');

        // Expect les appels save (1 pour l'annulation + 1 pour la nouvelle subscription)
        $this->subscriptionRepository
            ->expects($this->exactly(2))
            ->method('save');

        $result = $this->subscriptionService->changePlan($user, $newPlan);

        $this->assertInstanceOf(Subscription::class, $result);
        $this->assertEquals($user, $result->getUser());
        $this->assertEquals($newPlan, $result->getPlan());
        $this->assertEquals(Subscription::STATUS_ACTIVE, $result->getStatus());
    }
}
