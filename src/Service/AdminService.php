<?php

namespace App\Service;

use App\Dto\AdminStatsDto;
use App\Dto\UserListDto;
use App\Repository\AuditLogRepository;
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
        private readonly AuditLogRepository $auditLogRepository,
        private readonly AuthorizationService $authorizationService,
    ) {
    }

    /**
     * @return array<int, UserListDto>
     */
    public function listUsers(): array
    {
        if (! $this->authorizationService->isGranted('USER_VIEW_ALL')) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        // Utilisation de la méthode optimisée pour éviter le problème N+1
        $usersWithStats = $this->userRepository->findAllWithStats();

        $result = [];

        foreach ($usersWithStats as $userData) {
            $user = $this->userRepository->find($userData['id']);

            // Vérifier que l'utilisateur existe avant de créer le DTO
            if ($user === null) {
                continue; // Ignorer cet utilisateur s'il n'existe pas
            }

            $result[] = new UserListDto(
                $user,
                $userData['plan_name'] ?? 'Free',
                (int) $userData['forms_count'],
                (int) $userData['submissions_count']
            );
        }

        return $result;
    }

    public function getStats(): AdminStatsDto
    {
        if (! $this->authorizationService->isGranted('USER_VIEW_ALL')) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        $totalUsers = $this->userRepository->countNonAdminUsers();
        $totalForms = $this->formRepository->countAllForms();
        $totalSubmissions = $this->submissionRepository->countAllSubmissions();

        $usersPerMonth = $this->userRepository->getUsersPerMonth();

        $totalUsersPerMonth = [];
        $cumulative = 0;
        foreach ($usersPerMonth as $row) {
            $cumulative += $row['count'];
            $totalUsersPerMonth[] = [
                'month' => $row['month'],
                'count' => $cumulative,
            ];
        }

        $usersByPlan = $this->userRepository->getUsersByPlan();
        $recentAuditLogs = $this->auditLogRepository->getRecentAdminActions();

        return new AdminStatsDto(
            $totalUsers,
            $totalForms,
            $totalSubmissions,
            $usersPerMonth,
            $totalUsersPerMonth,
            $usersByPlan,
            $recentAuditLogs
        );
    }
}
