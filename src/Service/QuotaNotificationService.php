<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;

class QuotaNotificationService
{
    public function __construct(
        private EmailService $emailService,
        private QuotaStatusService $quotaStatusService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Vérifie et envoie les notifications pour un utilisateur
     * @param array<string, mixed> $quotaData
     */
    public function checkAndSendNotifications(User $user, array $quotaData): void
    {
        $currentMonth = new \DateTime('first day of this month');

        $quotaStatus = $this->quotaStatusService->getOrCreateQuotaStatus($user, $currentMonth);

        $usage = $quotaData['usage'];
        $this->quotaStatusService->updateUsageData(
            $quotaStatus,
            $usage['form_count'],
            $usage['submission_count'],
            $usage['storage_used_mb']
        );

        // Vérifier notification à 80%
        if ($this->shouldNotify($quotaData, 80) && ! $quotaStatus->isNotified80()) {
            if ($this->sendNotification($user, 80, $quotaData)) {
                $quotaStatus->setNotified80(true);
            }
        }

        // Vérifier notification à 100%
        if ($this->shouldNotify($quotaData, 100) && ! $quotaStatus->isNotified100()) {
            if ($this->sendNotification($user, 100, $quotaData)) {
                $quotaStatus->setNotified100(true);
            }
        }

        $this->quotaStatusService->flush();
    }

    /**
     * @param array<string, mixed> $quotaData
     */
    private function shouldNotify(array $quotaData, int $threshold): bool
    {
        $percentages = $quotaData['percentages'];

        return $percentages['forms_used_percent'] >= $threshold ||
               $percentages['submissions_used_percent'] >= $threshold ||
               $percentages['storage_used_percent'] >= $threshold;
    }

    /**
     * @param array<string, mixed> $quotaData
     */
    private function sendNotification(User $user, int $percentage, array $quotaData): bool
    {
        try {
            $email = $user->getEmail();
            if ($email === null) {
                throw new \RuntimeException('Utilisateur sans adresse email');
            }

            $this->emailService->sendQuotaAlert($user, $percentage, $quotaData);

            $this->logger->info('Notification quota envoyée', [
                'user_id' => $user->getId(),
                'percentage' => $percentage,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification quota', [
                'user_id' => $user->getId(),
                'percentage' => $percentage,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

}
