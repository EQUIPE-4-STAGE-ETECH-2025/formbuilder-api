<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\FormRepository;
use App\Repository\SubmissionRepository;
use App\Repository\SubscriptionRepository;

class QuotaService
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private FormRepository $formRepository,
        private SubmissionRepository $submissionRepository,
        private QuotaNotificationService $quotaNotificationService
    ) {
    }

    /**
     * Calcule les quotas en temps réel pour un utilisateur
     * @return array<string, mixed>
     */
    public function calculateCurrentQuotas(User $user): array
    {
        $currentMonth = new \DateTime('first day of this month');
        $currentMonth->setTime(0, 0, 0);

        // Récupérer l'abonnement actif
        $activeSubscription = $this->getActiveSubscription($user);
        $plan = $activeSubscription?->getPlan();

        if (! $plan) {
            throw new \RuntimeException('Aucun plan actif trouvé pour l\'utilisateur');
        }

        // Calculer l'utilisation actuelle
        $formCount = $this->formRepository->countByUser($user);
        $submissionCount = $this->submissionRepository->countByUserForMonth($user, $currentMonth);
        $storageUsedMb = 0; // Simplifié - pas de calcul de stockage pour l'instant

        $quotaData = [
            'user_id' => $user->getId(),
            'month' => $currentMonth->format('Y-m-d'),
            'limits' => [
                'max_forms' => $plan->getMaxForms(),
                'max_submissions_per_month' => $plan->getMaxSubmissionsPerMonth(),
                'max_storage_mb' => $plan->getMaxStorageMb(),
            ],
            'usage' => [
                'form_count' => $formCount,
                'submission_count' => $submissionCount,
                'storage_used_mb' => $storageUsedMb,
            ],
            'percentages' => [
                'forms_used_percent' => round(($formCount / $plan->getMaxForms()) * 100, 2),
                'submissions_used_percent' => round(($submissionCount / $plan->getMaxSubmissionsPerMonth()) * 100, 2),
                'storage_used_percent' => round(($storageUsedMb / $plan->getMaxStorageMb()) * 100, 2),
            ],
            'is_over_limit' => [
                'forms' => $formCount >= $plan->getMaxForms(),
                'submissions' => $submissionCount >= $plan->getMaxSubmissionsPerMonth(),
                'storage' => $storageUsedMb >= $plan->getMaxStorageMb(),
            ],
        ];

        // Vérifier et envoyer des notifications si nécessaire
        $this->quotaNotificationService->checkAndSendNotifications($user, $quotaData);

        return $quotaData;
    }

    /**
     * Vérifie si une action est autorisée selon les quotas
     */
    public function canPerformAction(User $user, string $actionType, int $quantity = 1): bool
    {
        $quotas = $this->calculateCurrentQuotas($user);

        switch ($actionType) {
            case 'create_form':
                return ! $quotas['is_over_limit']['forms'];

            case 'submit_form':
                return ($quotas['usage']['submission_count'] + $quantity) <= $quotas['limits']['max_submissions_per_month'];

            case 'upload_file':
                return ($quotas['usage']['storage_used_mb'] + $quantity) <= $quotas['limits']['max_storage_mb'];

            default:
                return false;
        }
    }

    /**
     * Bloque une action si les quotas sont dépassés
     */
    public function enforceQuotaLimit(User $user, string $actionType, int $quantity = 1): void
    {
        if (! $this->canPerformAction($user, $actionType, $quantity)) {
            $quotas = $this->calculateCurrentQuotas($user);

            switch ($actionType) {
                case 'create_form':
                    throw new \RuntimeException(sprintf(
                        'Limite de formulaires atteinte (%d/%d). Mettez à jour votre plan pour créer plus de formulaires.',
                        $quotas['usage']['form_count'],
                        $quotas['limits']['max_forms']
                    ));

                case 'submit_form':
                    throw new \RuntimeException(sprintf(
                        'Limite de soumissions atteinte (%d/%d) pour ce mois. Mettez à jour votre plan pour accepter plus de soumissions.',
                        $quotas['usage']['submission_count'],
                        $quotas['limits']['max_submissions_per_month']
                    ));

                case 'upload_file':
                    throw new \RuntimeException(sprintf(
                        'Limite de stockage atteinte (%d/%d MB). Mettez à jour votre plan pour plus d\'espace de stockage.',
                        $quotas['usage']['storage_used_mb'],
                        $quotas['limits']['max_storage_mb']
                    ));
            }
        }
    }

    private function getActiveSubscription(User $user): ?\App\Entity\Subscription
    {
        return $this->subscriptionRepository->findOneBy([
            'user' => $user,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }
}
