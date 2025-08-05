<?php

namespace App\Tests\Dto;

use App\Dto\PlanDto;
use App\Entity\Plan;
use PHPUnit\Framework\TestCase;

class PlanDtoTest extends TestCase
{
    public function testHydrationAndToArray(): void
    {
        $plan = new Plan();
        $plan->setId('plan-id-1');
        $plan->setName('Premium');
        $plan->setPriceCents(2900);
        $plan->setMaxForms(10);
        $plan->setMaxSubmissionsPerMonth(1000);
        $plan->setMaxStorageMb(50);

        $dto = new PlanDto($plan);

        $array = $dto->toArray();

        $this->assertEquals('plan-id-1', $array['id']);
        $this->assertEquals('Premium', $array['name']);
        $this->assertEquals(2900, $array['priceCents']); // Attention c'est bien priceCents
        $this->assertEquals(10, $array['maxForms']);
        $this->assertEquals(1000, $array['maxSubmissionsPerMonth']);
        $this->assertEquals(50, $array['maxStorageMb']);
    }
}