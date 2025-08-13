<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\DashboardService;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route('/api/dashboard/stats', name: 'dashboard_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $statsDto = $this->dashboardService->getUserStats($user);

        return $this->json($statsDto->toArray());
    }
}
