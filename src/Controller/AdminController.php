<?php

namespace App\Controller;

use App\Dto\UserListDto;
use App\Service\AdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    public function __construct(private readonly AdminService $adminService)
    {
    }

    #[Route('/api/admin/users', name: 'list_users', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        $users = $this->adminService->listUsers();

        $data = array_map(fn (UserListDto $dto) => $dto->toArray(), $users);

        return $this->json($data);
    }

    #[Route('/api/admin/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $stats = $this->adminService->getStats();

        return $this->json($stats);
    }
}
