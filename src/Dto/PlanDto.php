<?php

namespace App\Dto;

use App\Entity\Plan;

class PlanDto
{
    public string $id;
    public string $name;
    public int $priceCents;
    public int $maxForms;
    public int $maxSubmissionsPerMonth;
    public int $maxStorageMb;

    public function __construct(Plan $plan)
    {
        $this->id = $plan->getId();
        $this->name = $plan->getName();
        $this->priceCents = $plan->getPriceCents();
        $this->maxForms = $plan->getMaxForms();
        $this->maxSubmissionsPerMonth = $plan->getMaxSubmissionsPerMonth();
        $this->maxStorageMb = $plan->getMaxStorageMb();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'priceCents' => $this->priceCents,
            'maxForms' => $this->maxForms,
            'maxSubmissionsPerMonth' => $this->maxSubmissionsPerMonth,
            'maxStorageMb' => $this->maxStorageMb,
        ];
    }
}
