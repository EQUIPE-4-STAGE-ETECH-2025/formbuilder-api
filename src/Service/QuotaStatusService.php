<?php

namespace App\Service;

use App\Entity\QuotaStatus;
use App\Entity\User;
use App\Repository\QuotaStatusRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class QuotaStatusService
{
    public function __construct(
        private QuotaStatusRepository $quotaStatusRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getOrCreateQuotaStatus(User $user, DateTime $month): QuotaStatus
    {
        $normalizedMonth = clone $month;
        $normalizedMonth->setDate(
            (int) $normalizedMonth->format('Y'),
            (int) $normalizedMonth->format('n'),
            1
        )->setTime(0, 0, 0);

        $quotaStatus = $this->quotaStatusRepository->findOneBy([
            'user' => $user,
            'month' => $normalizedMonth,
        ]);

        if (! $quotaStatus) {
            $quotaStatus = new QuotaStatus();
            $quotaStatus->setId(Uuid::v4()->toRfc4122());
            $quotaStatus->setUser($user);
            $quotaStatus->setMonth($normalizedMonth);
            $quotaStatus->setFormCount(0);
            $quotaStatus->setSubmissionCount(0);
            $quotaStatus->setStorageUsedMb(0);
            $quotaStatus->setNotified80(false);
            $quotaStatus->setNotified100(false);

            $this->entityManager->persist($quotaStatus);

            $this->logger->info('Nouveau QuotaStatus créé automatiquement', [
                'user_id' => $user->getId(),
                'month' => $normalizedMonth->format('Y-m-d'),
            ]);
        }

        return $quotaStatus;
    }

    public function updateUsageData(QuotaStatus $quotaStatus, int $formCount, int $submissionCount, int $storageUsedMb): void
    {
        $quotaStatus->setFormCount($formCount);
        $quotaStatus->setSubmissionCount($submissionCount);
        $quotaStatus->setStorageUsedMb($storageUsedMb);

        $this->entityManager->persist($quotaStatus);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
