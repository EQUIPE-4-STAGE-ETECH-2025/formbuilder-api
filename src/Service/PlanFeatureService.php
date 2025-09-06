<?php

namespace App\Service;

use App\Entity\Plan;
use App\Repository\PlanRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PlanFeatureService
{
    public function __construct(
        private PlanRepository $planRepository
    ) {
    }

    /**
     * Retourne toutes les fonctionnalités d'un plan.
     *
     * @return array<int, array<string, string|null>>
     */
    public function getFeatures(string $planId): array
    {
        $plan = $this->findPlanOrFail($planId);

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
     * Vérifie si un plan possède une fonctionnalité donnée.
     */
    public function hasFeature(string $planId, string $featureCode): bool
    {
        $plan = $this->findPlanOrFail($planId);

        foreach ($plan->getPlanFeatures() as $planFeature) {
            $feature = $planFeature->getFeature();
            if ($feature !== null && $feature->getCode() !== null) {
                if (strtolower($feature->getCode()) === strtolower($featureCode)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retourne la limite associée à une fonctionnalité du plan.
     */
    public function getLimit(string $planId, string $featureCode): ?int
    {
        $plan = $this->findPlanOrFail($planId);

        foreach ($plan->getPlanFeatures() as $planFeature) {
            $feature = $planFeature->getFeature();
            if ($feature !== null && $feature->getCode() !== null) {
                $featureCodeLower = strtolower($feature->getCode());

                if ($featureCodeLower === strtolower($featureCode)) {
                    // On mappe les codes des features aux champs du plan
                    return match ($featureCodeLower) {
                        'max_forms' => $plan->getMaxForms(),
                        'max_submissions_per_month' => $plan->getMaxSubmissionsPerMonth(),
                        'max_storage_mb' => $plan->getMaxStorageMb(),
                        default => null,
                    };
                }
            }
        }

        return null;
    }

    /**
     * Trouve un plan ou lance une exception 404.
     */
    private function findPlanOrFail(string $planId): Plan
    {
        $plan = $this->planRepository->find($planId);

        if (! $plan) {
            throw new NotFoundHttpException("Plan introuvable.");
        }

        return $plan;
    }
}
