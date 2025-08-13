<?php

namespace App\Service;

use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminService
{
    public function __construct(
        private readonly UserRepository       $userRepository,
        private readonly AuthorizationService $authorizationService,
    )
    {
    }

    public function listUsers(): array
    {
        if (!$this->authorizationService->isGranted('USER_VIEW_ALL')) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        return $this->userRepository->findAll();
    }
}
