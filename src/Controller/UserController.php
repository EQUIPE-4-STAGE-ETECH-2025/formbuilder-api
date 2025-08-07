<?php

namespace App\Controller;

use App\Dto\UserResponseDto;
use App\Service\UserService;
use InvalidArgumentException;
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

        if (! $newRole) {
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

    #[Route('/api/users/{id}/profile', name: 'get_user_profile', methods: ['GET'])]
    public function getProfile(string $id): JsonResponse
    {
        $user = $this->userService->getUserProfile($id);

        return $this->json([
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'role' => $user->getRole(),
            'isEmailVerified' => $user->isEmailVerified(),
        ]);
    }

    #[Route('/api/users/{id}/profile', name: 'update_user_profile', methods: ['PUT'])]
    public function updateProfile(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $user = $this->userService->updateUserProfile($id, $data);
        } catch (InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true);

            return $this->json(['errors' => $errors], 422);
        }

        return $this->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => [
                'id' => (string) $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'role' => $user->getRole(),
                'isEmailVerified' => $user->isEmailVerified(),
            ],
        ]);
    }

    #[Route('/api/users/{id}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(string $id): JsonResponse
    {
        try {
            $user = $this->userService->getUserProfile($id);

            $userDto = new UserResponseDto($user);

            $this->userService->deleteUser($id);

            return $this->json([
                'message' => 'Utilisateur supprimé avec succès',
                'user' => $userDto->toArray(),
            ]);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }
    }
}