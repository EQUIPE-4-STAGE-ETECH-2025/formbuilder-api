<?php

namespace App\Controller;

use App\Service\PlanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route; 

class PlanController extends AbstractController
{
    private PlanService $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    #[Route('/api/plans', name: 'get_plans', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $plans = $this->planService->getAllPlans();

        $data = array_map(fn($planDto) => $planDto->toArray(), $plans);

        return $this->json($data);
    }
}
