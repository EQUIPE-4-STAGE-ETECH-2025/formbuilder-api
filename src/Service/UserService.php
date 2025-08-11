<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AuthorizationService $authorizationService,
    ) {
    }

    public function getUserRole(string $targetUserId): ?string
    {
        $targetUser = $this->userRepository->find($targetUserId);
        if (!$targetUser) {
            throw new NotFoundHttpException('Utilisateur non trouvé.');
        }

        $this->authorizationService->requirePermissionOnObject('USER_VIEW_ROLE', $targetUser);

        return $targetUser->getRole();
    }

    public function updateUserRole(string $targetUserId, string $newRole): User
    {
        $targetUser = $this->userRepository->find($targetUserId);
        if (!$targetUser) {
            throw new NotFoundHttpException('Utilisateur non trouvé.');
        }

        $this->authorizationService->requirePermissionOnObject('USER_EDIT_ROLE', $targetUser);

        $targetUser->setRole($newRole);
        $targetUser->setUpdatedAt(new \DateTimeImmutable());
        $this->userRepository->save($targetUser, true);

        return $targetUser;
    }


    public function getUserProfile(string $id): User
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException('Utilisateur non trouvé.');
        }

        $this->authorizationService->requirePermissionOnObject('USER_VIEW_PROFILE', $user);

        return $user;
    }
}
