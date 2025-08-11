<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthCheckController extends AbstractController
{
    #[Route('/ping', name: 'system_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return $this->json(['status' => 'pong']);
    }
}
