<?php

namespace App\Dto;

class AdminStatsDto
{
    public int $totalUsers;
    public int $totalForms;
    public int $totalSubmissions;
    public array $usersPerMonth = [];
    public array $totalUsersPerMonth = [];
    public array $usersByPlan = [];
    public array $recentAuditLogs = [];

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
