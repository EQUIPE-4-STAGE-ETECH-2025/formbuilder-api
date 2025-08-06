<?php

namespace App\Tests\Service;

use App\Dto\PlanDto;
use App\Entity\Plan;
use App\Repository\PlanRepository;
use App\Service\PlanService;
use PHPUnit\Framework\TestCase;

class PlanServiceTest extends TestCase
{
    private PlanRepository $planRepository;
    private PlanService $planService;

    protected function setUp(): void
    {
        // Mock du PlanRepository
        $this->planRepository = $this->createMock(PlanRepository::class);
        $this->planService = new PlanService($this->planRepository);
    }

    public function testGetAllPlansReturnsSortedPlanDtos(): void
    {
        // Mock Plan 1 : Free
        $planFree = $this->createMock(Plan::class);
        $planFree->method('getId')->willReturn('1');
        $planFree->method('getName')->willReturn('Free');
        $planFree->method('getPriceCents')->willReturn(0);
        $planFree->method('getMaxForms')->willReturn(3);
        $planFree->method('getMaxSubmissionsPerMonth')->willReturn(500);
        $planFree->method('getMaxStorageMb')->willReturn(10);

        // Mock Plan 2 : Premium
        $planPremium = $this->createMock(Plan::class);
        $planPremium->method('getId')->willReturn('2');
        $planPremium->method('getName')->willReturn('Premium');
        $planPremium->method('getPriceCents')->willReturn(2900);
        $planPremium->method('getMaxForms')->willReturn(20);
        $planPremium->method('getMaxSubmissionsPerMonth')->willReturn(10000);
        $planPremium->method('getMaxStorageMb')->willReturn(100);

        // Mock Plan 3 : Pro
        $planPro = $this->createMock(Plan::class);
        $planPro->method('getId')->willReturn('3');
        $planPro->method('getName')->willReturn('Pro');
        $planPro->method('getPriceCents')->willReturn(9900);
        $planPro->method('getMaxForms')->willReturn(-1);
        $planPro->method('getMaxSubmissionsPerMonth')->willReturn(100000);
        $planPro->method('getMaxStorageMb')->willReturn(500);

        $plans = [$planFree, $planPremium, $planPro];

        // On simule le repository pour retourner ces plans
        $this->planRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([], ['priceCents' => 'ASC'])
            ->willReturn($plans);

        // Execution du service
        $result = $this->planService->getAllPlans();

        // Assertions
        $this->assertCount(3, $result);
        $this->assertContainsOnlyInstancesOf(PlanDto::class, $result);

        // Vérifie l’ordre de tri par prix
        $this->assertEquals('Free', $result[0]->name);
        $this->assertEquals('Premium', $result[1]->name);
        $this->assertEquals('Pro', $result[2]->name);

        // Vérifie aussi les prix pour être sûr
        $this->assertEquals(0, $result[0]->priceCents);
        $this->assertEquals(2900, $result[1]->priceCents);
        $this->assertEquals(9900, $result[2]->priceCents);
    }
}
