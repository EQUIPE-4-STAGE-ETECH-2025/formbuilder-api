<?php

namespace App\Service;

use App\Repository\FormRepository;
use App\Repository\SubmissionRepository;

class SubmissionAnalyticsService
{
    public function __construct(
        private SubmissionRepository $submissionRepository,
        private FormRepository $formRepository,
    ) {
    }

    /**
     * Retourne les statistiques d'un formulaire.
     *
     * @return array<string, mixed>
     */
    public function getFormAnalytics(string $formId): array
    {
        $form = $this->formRepository->find($formId);
        if (! $form) {
            throw new \InvalidArgumentException('Formulaire introuvable.');
        }

        $submissions = $this->submissionRepository->findBy(['form' => $form]);
        $totalSubmissions = count($submissions);

        // Evolution des soumissions par date (7 derniers jours)
        $dailyCounts = [];
        $now = new \DateTimeImmutable();
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->modify("-$i days")->format('Y-m-d');
            $dailyCounts[$day] = 0;
        }

        foreach ($submissions as $submission) {
            $submittedAt = $submission->getSubmittedAt();
            if ($submittedAt !== null) {
                $day = $submittedAt->format('Y-m-d');
                if (isset($dailyCounts[$day])) {
                    $dailyCounts[$day]++;
                }
            }
        }

        // Calcul des métriques de conversion
        $totalVisitors = $this->getTotalVisitors($formId); // Simulé pour l'instant
        $conversionRate = $this->calculateConversionRate($totalSubmissions, $totalVisitors);

        // Analyse des performances
        $averageSubmissionTime = $this->calculateAverageSubmissionTime($submissions);

        return [
            'total' => $totalSubmissions,
            'daily' => $dailyCounts,
            'conversion_rate' => $conversionRate,
            'average_submission_time' => $averageSubmissionTime,
        ];
    }

    /**
     * Simule le nombre total de visiteurs d’un formulaire.
     * Remplacez cette méthode par une vraie implémentation si disponible.
     */
    private function getTotalVisitors(string $formId): int
    {
        // Simule un total de visiteurs pour le formulaire
        return 100; // Exemple : 100 visiteurs
    }

    /**
     * Calcule le taux de conversion.
     */
    private function calculateConversionRate(int $totalSubmissions, int $totalVisitors): float
    {
        if ($totalVisitors === 0) {
            return 0.0;
        }

        return round(($totalSubmissions / $totalVisitors) * 100, 2); // En pourcentage
    }

    /**
     * Calcule le temps moyen de soumission (en secondes).
     *
     * @param array<int, \App\Entity\Submission> $submissions
     */
    private function calculateAverageSubmissionTime(array $submissions): ?float
    {
        if (empty($submissions)) {
            return null;
        }

        $totalTime = 0;
        $count = 0;

        foreach ($submissions as $submission) {
            $form = $submission->getForm();
            $createdAt = $form?->getCreatedAt(); // Assurez-vous que `getCreatedAt` existe
            $submittedAt = $submission->getSubmittedAt();

            if ($createdAt && $submittedAt) {
                $totalTime += $submittedAt->getTimestamp() - $createdAt->getTimestamp();
                $count++;
            }
        }

        return $count > 0 ? round($totalTime / $count, 2) : null;
    }
}
