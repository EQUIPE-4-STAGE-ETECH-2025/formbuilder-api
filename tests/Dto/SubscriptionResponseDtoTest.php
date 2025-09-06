<?php

namespace App\Tests\Dto;

use App\Dto\SubscriptionResponseDto;
use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class SubscriptionResponseDtoTest extends TestCase
{
    public function testHydrationAndToArray(): void
    {
        // Mock de l'utilisateur
        $user = new User();
        $user->setId('user-id-1');

        // Mock du Plan
        $plan = new Plan();
        $plan->setId('plan-id-1');
        $plan->setName('Pro');
        $plan->setPriceCents(9900);
        $plan->setMaxForms(-1);
        $plan->setMaxSubmissionsPerMonth(100000);
        $plan->setMaxStorageMb(500);

        // Mock de Subscription avec User et Plan liés
        $subscription = new Subscription();
        $subscription->setId('sub-id-1');
        $subscription->setUser($user);
        $subscription->setPlan($plan);
        $subscription->setStripeSubscriptionId('stripe-sub-123');
        $subscription->setStartDate(new \DateTime('2025-01-01'));
        $subscription->setEndDate(new \DateTime('2026-01-01'));
        $subscription->setStatus(Subscription::STATUS_ACTIVE);

        // DTO
        $dto = new SubscriptionResponseDto($subscription);
        $array = $dto->toArray();

        // Assertions
        $this->assertEquals('sub-id-1', $array['id']);
        $this->assertEquals('user-id-1', $array['userId']);
        $this->assertEquals('Pro', $array['planName']);
        $this->assertEquals('stripe-sub-123', $array['stripeSubscriptionId']);
        $this->assertEquals('2025-01-01', $array['startDate']);
        $this->assertEquals('2026-01-01', $array['endDate']);
        $this->assertTrue($array['isActive']);
    }
}
