<?php

namespace App\Service;

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
        if (!$this->authorizationChecker->isGranted($permission, $subject)) {
            throw new AccessDeniedHttpException("Accès refusé : permission $permission requise.");
        }
    }
}
