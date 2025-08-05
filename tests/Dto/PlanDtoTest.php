<?php

namespace App\Tests\Dto;

use App\Dto\PlanDto;
use PHPUnit\Framework\TestCase;

class PlanDtoTest extends TestCase
{
    public function testHydrationAndToArray(): void
    {
        $dto = new PlanDto(
            'plan-id-1',
            'Premium',
            2900,
            10,
            1000,
            50
        );

        $array = $dto->toArray();

        $this->assertEquals('plan-id-1', $array['id']);
        $this->assertEquals('Premium', $array['name']);
        $this->assertEquals(29.00, $array['price']);
        $this->assertEquals(10, $array['maxForms']);
        $this->assertEquals(1000, $array['maxSubmissionsPerMonth']);
        $this->assertEquals(50, $array['maxStorageMb']);
    }
}
