<?php

namespace App\Service;

use App\Entity\Form;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AuthorizationService
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function requirePermissionOnObject(string $permission, User $subject): void
    {
        if (! $this->authorizationChecker->isGranted($permission, $subject)) {
            throw new AccessDeniedHttpException("Accès refusé : permission $permission requise.");
        }
    }

    public function isGranted(string $permission, mixed $subject = null): bool
    {
        return $this->authorizationChecker->isGranted($permission, $subject);
    }

    public function canAccessForm(User $user, Form $form): bool
    {
        $formUser = $form->getUser();
        if ($formUser === null) {
            return false;
        }

        // L'utilisateur peut accéder à ses propres formulaires
        if ($formUser->getId() === $user->getId()) {
            return true;
        }

        // Les administrateurs peuvent accéder à tous les formulaires
        if ($user->getRole() === 'ADMIN') {
            return true;
        }

        return false;
    }

    public function canModifyForm(User $user, Form $form): bool
    {
        $formUser = $form->getUser();
        if ($formUser === null) {
            return false;
        }

        // L'utilisateur peut modifier ses propres formulaires
        if ($formUser->getId() === $user->getId()) {
            return true;
        }

        // Les administrateurs peuvent modifier tous les formulaires
        if ($user->getRole() === 'ADMIN') {
            return true;
        }

        return false;
    }
}
