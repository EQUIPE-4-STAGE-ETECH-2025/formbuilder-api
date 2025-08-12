<?php

namespace App\Service;

use App\Entity\Plan;
use App\Repository\PlanRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PlanFeatureService
{
    public function __construct(
        private PlanRepository $planRepository
    ) {}

    /**
     * Retourne toutes les fonctionnalités d’un plan.
     *
     * @return array
     */
    public function getFeatures(string $planId): array
    {
        $plan = $this->findPlanOrFail($planId);

        $features = [];
        foreach ($plan->getPlanFeatures() as $planFeature) {
            $feature = $planFeature->getFeature();
            $features[] = [
                'id' => $feature->getId(),
                'code' => $feature->getCode(),
                'label' => $feature->getLabel(),
            ];
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
            if (strtolower($planFeature->getFeature()->getCode()) === strtolower($featureCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dans notre cas actuel, on retourne juste null car il n'y a pas de limite
     * spécifique stockée par fonctionnalité.
     */
    public function getLimit(string $planId, string $featureCode): ?int
    {
        $plan = $this->findPlanOrFail($planId);

        foreach ($plan->getPlanFeatures() as $planFeature) {
            if (strtolower($planFeature->getFeature()->getCode()) === strtolower($featureCode)) {
                return $planFeature->getLimitValue(); // retourne la limite ou null
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

        if (!$plan) {
            throw new NotFoundHttpException("Plan introuvable.");
        }

        return $plan;
    }
}



