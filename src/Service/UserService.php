<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AuthorizationService $authorizationService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function getUserRole(string $targetUserId): ?string
    {
        $targetUser = $this->userRepository->find($targetUserId);
        if (! $targetUser) {
            throw new NotFoundHttpException('Utilisateur non trouvé.');
        }

        $this->authorizationService->requirePermissionOnObject('USER_VIEW_ROLE', $targetUser);

        return $targetUser->getRole();
    }

    public function updateUserRole(string $targetUserId, string $newRole): User
    {
        $targetUser = $this->userRepository->find($targetUserId);
        if (! $targetUser) {
            throw new NotFoundHttpException('Utilisateur non trouvé.');
        }

        $this->authorizationService->requirePermissionOnObject('USER_EDIT_ROLE', $targetUser);

        $targetUser->setRole($newRole);
        $targetUser->setUpdatedAt(new DateTimeImmutable());
        $this->userRepository->save($targetUser, true);

        return $targetUser;
    }


    public function getUserProfile(string $id): User
    {
        $user = $this->userRepository->find($id);
        if (! $user) {
            throw new NotFoundHttpException('Utilisateur non trouvé.');
        }

        $this->authorizationService->requirePermissionOnObject('USER_VIEW_PROFILE', $user);

        return $user;
    }

    public function updateUserProfile(string $id, array $data): User
    {
        $user = $this->userRepository->find($id);
        if (! $user) {
            throw new NotFoundHttpException('Utilisateur non trouvé.');
        }

        $this->authorizationService->requirePermissionOnObject('USER_EDIT_PROFILE', $user);

        if (isset($data['firstName'])) {
            $user->setFirstName(trim($data['firstName']));
        }
        if (isset($data['lastName'])) {
            $user->setLastName(trim($data['lastName']));
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            throw new InvalidArgumentException(json_encode($messages));
        }

        $user->setUpdatedAt(new DateTimeImmutable());
        $this->userRepository->save($user, true);

        return $user;
    }

    public function listUsers(): array
    {
        if (! $this->authorizationService->isGranted('USER_VIEW_ALL')) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        return $this->userRepository->findAll();
    }

    public function deleteUser(string $id): void
    {
        $user = $this->userRepository->find($id);
        if (! $user) {
            throw new NotFoundHttpException('Utilisateur non trouvé.');
        }

        $this->authorizationService->requirePermissionOnObject('USER_DELETE', $user);

        $this->userRepository->remove($user, true);
    }

}
