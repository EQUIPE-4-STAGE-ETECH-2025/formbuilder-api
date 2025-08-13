<?php

namespace App\Dto;

class DashboardStatsDto
{
    private int $totalForms;
    private int $publishedForms;
    private int $totalSubmissions;

    /** @var array<string,int> nombre de soumissions par mois */
    private array $submissionsPerMonth;

    /** @var array<string,int> nombre de soumissions par formulaire */
    private array $submissionsPerForm;

    /** @var array<string,int> nombre de formulaires par statut */
    private array $formsStatusCount;

    /** @var array<int, array<string, mixed>> */
    private array $recentSubmissions;

    public function __construct(
        int $totalForms,
        int $publishedForms,
        int $totalSubmissions,
        array $submissionsPerMonth = [],
        array $submissionsPerForm = [],
        array $formsStatusCount = [],
        array $recentSubmissions = []
    ) {
        $this->totalForms = $totalForms;
        $this->publishedForms = $publishedForms;
        $this->totalSubmissions = $totalSubmissions;
        $this->submissionsPerMonth = $submissionsPerMonth;
        $this->submissionsPerForm = $submissionsPerForm;
        $this->formsStatusCount = $formsStatusCount;
        $this->recentSubmissions = $recentSubmissions;
    }

    public function toArray(): array
    {
        return [
            'totalForms' => $this->totalForms,
            'publishedForms' => $this->publishedForms,
            'totalSubmissions' => $this->totalSubmissions,
            'submissionsPerMonth' => $this->submissionsPerMonth,
            'submissionsPerForm' => $this->submissionsPerForm,
            'formsStatusCount' => $this->formsStatusCount,
            'recentSubmissions' => $this->recentSubmissions,
        ];
    }
}
