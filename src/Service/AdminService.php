<?php

namespace App\Service;

use App\Dto\UserListDto;
use App\Repository\FormRepository;
use App\Repository\SubmissionRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminService
{
    public function __construct(
        private readonly UserRepository       $userRepository,
        private readonly FormRepository       $formRepository,
        private readonly SubmissionRepository $submissionRepository,
        private readonly AuthorizationService $authorizationService,
    )
    {
    }

    public function listUsers(): array
    {
        if (!$this->authorizationService->isGranted('USER_VIEW_ALL')) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        $users = $this->userRepository->findAll();

        $result = [];

        foreach ($users as $user) {
            if ($user->getRole() === 'ADMIN') {
                continue;
            }

            $formsCount = $this->formRepository->count(['user' => $user->getId()]);

            $submissionsCount = $this->submissionRepository->countByUserForms($user->getId());

            $planName = $this->userRepository->getPlanNameForUser($user);

            $result[] = new UserListDto($user, $planName, $formsCount, $submissionsCount);
        }

        return $result;
    }
}
