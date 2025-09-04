<?php

namespace App\Service;

use App\Dto\PlanDto;
use App\Repository\PlanRepository;

class PlanService
{
    public function __construct(
        private PlanRepository $planRepository
    ) {}

    /**
     * Récupère tous les plans triés par prix (croissant).
     *
     * @return PlanDto[]
     */
    public function getAllPlans(): array
    {
        $plans = $this->planRepository->findBy([], ['priceCents' => 'ASC']);

        return array_map(fn($plan) => new PlanDto($plan), $plans);
    }
}
