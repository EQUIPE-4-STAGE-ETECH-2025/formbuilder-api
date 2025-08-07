<?php

namespace App\Controller;

use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(private readonly UserService $userService)
    {
    }

    #[Route('/api/users/{id}/roles', name: 'get_user_role', methods: ['GET'])]
    public function getRole(string $id): JsonResponse
    {
        $role = $this->userService->getUserRole($id);

        return $this->json(['role' => $role]);
    }

    #[Route('/api/users/{id}/roles', name: 'update_user_role', methods: ['PUT'])]
    public function updateRole(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newRole = $data['role'] ?? null;

        if (!$newRole) {
            return $this->json(['error' => 'Rôle invalide'], 422);
        }

        $user = $this->userService->updateUserRole($id, $newRole);

        return $this->json([
            'message' => 'Rôle mis à jour avec succès',
            'user' => [
                'id' => (string) $user->getId(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
            ],
        ]);
    }
}
