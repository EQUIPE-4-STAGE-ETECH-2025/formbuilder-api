<?php

namespace App\Service;

use App\Dto\DashboardStatsDto;
use App\Entity\User;
use App\Repository\FormRepository;
use App\Repository\SubmissionRepository;
use Doctrine\DBAL\Exception;

class DashboardService
{
    public function __construct(
        private readonly FormRepository $formRepository,
        private readonly SubmissionRepository $submissionRepository,
    ) {
    }

    /**
     * @throws Exception
     */
    public function getUserStats(User $user): DashboardStatsDto
    {
        $userId = $user->getId();

        $totalForms = $this->formRepository->count(['user' => $userId]);

        $publishedForms = $this->formRepository->count([
            'user' => $userId,
            'status' => 'PUBLISHED',
        ]);

        $totalSubmissions = $this->submissionRepository->countByUserForms($userId);

        $submissionsPerMonth = $this->submissionRepository->countSubmissionsPerMonthByUser($userId);

        $submissionsPerForm = $this->submissionRepository->countSubmissionsPerFormByUser($userId);

        $formsStatusCount = $this->formRepository->countFormsByStatusForUser($userId);

        return new DashboardStatsDto(
            $totalForms,
            $publishedForms,
            $totalSubmissions,
            $submissionsPerMonth,
            $submissionsPerForm,
            $formsStatusCount,
        );
    }

}
