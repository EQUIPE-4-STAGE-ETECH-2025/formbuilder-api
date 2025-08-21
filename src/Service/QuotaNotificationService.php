<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\QuotaStatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class QuotaNotificationService
{
    public function __construct(
        private EmailService $emailService,
        private QuotaStatusRepository $quotaStatusRepository,
        private EntityManagerInterface $entityManager,
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
        $quotaStatus = $this->quotaStatusRepository->findOneBy([
            'user' => $user,
            'month' => $currentMonth,
        ]);

        if (! $quotaStatus) {
            return;
        }

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

        $this->entityManager->persist($quotaStatus);
        $this->entityManager->flush();
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
            $subject = sprintf('Alerte quota - %d%% atteint - FormBuilder', $percentage);
            $message = $this->buildMessage($user, $percentage, $quotaData);

            $email = $user->getEmail();
            if ($email === null) {
                throw new \RuntimeException('Utilisateur sans adresse email');
            }
            $this->emailService->sendEmail($email, $subject, $message);

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

    /**
     * @param array<string, mixed> $quotaData
     */
    private function buildMessage(User $user, int $percentage, array $quotaData): string
    {
        $firstName = $user->getFirstName() ?? 'Cher utilisateur';

        $message = sprintf('<p>Bonjour %s,</p>', htmlspecialchars($firstName));
        $message .= sprintf('<p>Vous avez atteint %d%% d\'utilisation de vos quotas.</p>', $percentage);

        $message .= '<h3>Détails :</h3><ul>';
        $message .= sprintf(
            '<li>Formulaires : %d/%d</li>',
            $quotaData['usage']['form_count'],
            $quotaData['limits']['max_forms']
        );
        $message .= sprintf(
            '<li>Soumissions : %d/%d</li>',
            $quotaData['usage']['submission_count'],
            $quotaData['limits']['max_submissions_per_month']
        );
        $message .= sprintf(
            '<li>Stockage : %d/%d MB</li>',
            $quotaData['usage']['storage_used_mb'],
            $quotaData['limits']['max_storage_mb']
        );
        $message .= '</ul>';

        if ($percentage >= 100) {
            $message .= '<p><strong>Certaines fonctionnalités peuvent être limitées.</strong></p>';
        }

        $message .= '<p>Cordialement,<br>L\'équipe FormBuilder</p>';

        return $message;
    }
}
