<?php

namespace App\Dto;

class AdminStatsDto
{
    public int $totalUsers;
    public int $totalForms;
    public int $totalSubmissions;

    /** @var array<int, array<string, mixed>> */
    public array $usersPerMonth = [];

    /** @var array<int, array<string, mixed>> */
    public array $totalUsersPerMonth = [];

    /** @var array<int, array<string, mixed>> */
    public array $usersByPlan = [];

    /** @var array<int, array<string, mixed>> */
    public array $recentAuditLogs = [];

    /**
     * @param array<int, array<string, mixed>> $usersPerMonth
     * @param array<int, array<string, mixed>> $totalUsersPerMonth
     * @param array<int, array<string, mixed>> $usersByPlan
     * @param array<int, array<string, mixed>> $recentAuditLogs
     */
    public function __construct(
        int $totalUsers,
        int $totalForms,
        int $totalSubmissions,
        array $usersPerMonth,
        array $totalUsersPerMonth,
        array $usersByPlan,
        array $recentAuditLogs
    ) {
        $this->totalUsers = $totalUsers;
        $this->totalForms = $totalForms;
        $this->totalSubmissions = $totalSubmissions;
        $this->usersPerMonth = $usersPerMonth;
        $this->totalUsersPerMonth = $totalUsersPerMonth;
        $this->usersByPlan = $usersByPlan;
        $this->recentAuditLogs = $recentAuditLogs;
    }
}
