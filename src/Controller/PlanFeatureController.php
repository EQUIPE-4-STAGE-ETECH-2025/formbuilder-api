<?php

namespace App\Controller;

use App\Service\PlanFeatureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PlanFeatureController extends AbstractController
{
    public function __construct(private PlanFeatureService $planFeatureService)
    {
    }

    #[Route('/api/plans/{planId}/features', name: 'plan_features_list', methods: ['GET'])]
    public function listFeatures(string $planId): JsonResponse
    {
        $features = $this->planFeatureService->getFeatures($planId);

        return $this->json($features);
    }
}
