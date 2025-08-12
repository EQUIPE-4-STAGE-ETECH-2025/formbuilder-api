<?php

namespace App\Tests\Service;

use App\Entity\Plan;
use App\Entity\PlanFeature;
use App\Entity\Feature;
use App\Repository\PlanRepository;
use App\Service\PlanFeatureService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\Common\Collections\ArrayCollection;


class PlanFeatureServiceTest extends TestCase
{
    private $planRepository;
    private PlanFeatureService $service;

    protected function setUp(): void
    {
        $this->planRepository = $this->createMock(PlanRepository::class);
        $this->service = new PlanFeatureService($this->planRepository);
    }

    public function testGetFeaturesReturnsArray()
    {
        $feature = $this->createMock(Feature::class);
        $feature->method('getId')->willReturn('feat-1');
        $feature->method('getCode')->willReturn('FEATURE_CODE');
        $feature->method('getLabel')->willReturn('Feature Label');

        $planFeature = $this->createMock(PlanFeature::class);
        $planFeature->method('getFeature')->willReturn($feature);

        $plan = $this->createMock(Plan::class);
        $plan->method('getPlanFeatures')->willReturn(new ArrayCollection([$planFeature]));
        $this->planRepository->method('find')->willReturn($plan);

        $features = $this->service->getFeatures('plan-id');

        $this->assertIsArray($features);
        $this->assertCount(1, $features);
        $this->assertEquals('feat-1', $features[0]['id']);
        $this->assertEquals('FEATURE_CODE', $features[0]['code']);
        $this->assertEquals('Feature Label', $features[0]['label']);
    }

    public function testHasFeatureReturnsTrue()
    {
        $feature = $this->createMock(Feature::class);
        $feature->method('getCode')->willReturn('FEATURE_CODE');

        $planFeature = $this->createMock(PlanFeature::class);
        $planFeature->method('getFeature')->willReturn($feature);

        $plan = $this->createMock(Plan::class);
        $plan->method('getPlanFeatures')->willReturn(new ArrayCollection([$planFeature]));

        $this->planRepository->method('find')->willReturn($plan);

        $this->assertTrue($this->service->hasFeature('plan-id', 'FEATURE_CODE'));
        $this->assertTrue($this->service->hasFeature('plan-id', 'feature_code')); // insensitive test
    }

    public function testHasFeatureReturnsFalse()
    {
        $feature = $this->createMock(Feature::class);
        $feature->method('getCode')->willReturn('OTHER_CODE');

        $planFeature = $this->createMock(PlanFeature::class);
        $planFeature->method('getFeature')->willReturn($feature);

        $plan = $this->createMock(Plan::class);
        $plan->method('getPlanFeatures')->willReturn(new ArrayCollection([$planFeature]));

        $this->planRepository->method('find')->willReturn($plan);

        $this->assertFalse($this->service->hasFeature('plan-id', 'FEATURE_CODE'));
    }

    public function testGetLimitReturnsValue()
    {
        $feature = $this->createMock(Feature::class);
        $feature->method('getCode')->willReturn('FEATURE_CODE');

        $planFeature = $this->createMock(PlanFeature::class);
        $planFeature->method('getFeature')->willReturn($feature);
        $planFeature->method('getLimitValue')->willReturn(10);

        $plan = $this->createMock(Plan::class);
        $plan->method('getPlanFeatures')->willReturn(new ArrayCollection([$planFeature]));

        $this->planRepository->method('find')->willReturn($plan);

        $this->assertEquals(10, $this->service->getLimit('plan-id', 'FEATURE_CODE'));
    }

    public function testGetLimitReturnsNull()
    {
        $feature = $this->createMock(Feature::class);
        $feature->method('getCode')->willReturn('OTHER_CODE');

        $planFeature = $this->createMock(PlanFeature::class);
        $planFeature->method('getFeature')->willReturn($feature);
        $planFeature->method('getLimitValue')->willReturn(null);

        $plan = $this->createMock(Plan::class);
        $plan->method('getPlanFeatures')->willReturn(new ArrayCollection([$planFeature]));

        $this->planRepository->method('find')->willReturn($plan);

        $this->assertNull($this->service->getLimit('plan-id', 'FEATURE_CODE'));
    }

    public function testGetFeaturesThrowsNotFoundException()
    {
        $this->planRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->getFeatures('invalid-plan-id');
    }
}
