<?php

namespace App\Dto;

use App\Entity\Plan;

class PlanDto
{
    public string $id;
    public string $name;
    public int $priceCents;
    public string $stripeProductId;
    public ?string $stripePriceId;
    public int $maxForms;
    public int $maxSubmissionsPerMonth;
    public int $maxStorageMb;
    /** @var array<int, array<string, string|null>> */
    public array $features;

    public function __construct(Plan $plan)
    {
        $this->id = $plan->getId() ?? '';
        $this->name = $plan->getName() ?? '';
        $this->priceCents = $plan->getPriceCents() ?? 0;
        $this->stripeProductId = $plan->getStripeProductId() ?? '';
        $this->stripePriceId = $plan->getStripePriceId();
        $this->maxForms = $plan->getMaxForms() ?? 0;
        $this->maxSubmissionsPerMonth = $plan->getMaxSubmissionsPerMonth() ?? 0;
        $this->maxStorageMb = $plan->getMaxStorageMb() ?? 0;
        $this->features = $this->extractFeatures($plan);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function extractFeatures(Plan $plan): array
    {
        $features = [];
        foreach ($plan->getPlanFeatures() as $planFeature) {
            $feature = $planFeature->getFeature();
            if ($feature !== null) {
                $features[] = [
                    'id' => $feature->getId(),
                    'code' => $feature->getCode(),
                    'label' => $feature->getLabel(),
                ];
            }
        }

        return $features;
    }


    /**
     * @return array<string, string|int|null|array<int, array<string, string|null>>>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'priceCents' => $this->priceCents,
            'stripeProductId' => $this->stripeProductId,
            'stripePriceId' => $this->stripePriceId,
            'maxForms' => $this->maxForms,
            'maxSubmissionsPerMonth' => $this->maxSubmissionsPerMonth,
            'maxStorageMb' => $this->maxStorageMb,
            'features' => $this->features,
        ];
    }
}
