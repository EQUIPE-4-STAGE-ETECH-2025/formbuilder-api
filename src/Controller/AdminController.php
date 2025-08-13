<?php

namespace App\Controller;

use App\Dto\UserResponseDto;
use App\Service\AdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    public function __construct(private readonly AdminService $adminService)
    {}

    #[Route('/api/admin/users', name: 'list_users', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        $users = $this->adminService->listUsers();

        $result = array_map(fn($user) => (new UserResponseDto($user))->toArray(), $users);

        return $this->json($result);
    }
}
