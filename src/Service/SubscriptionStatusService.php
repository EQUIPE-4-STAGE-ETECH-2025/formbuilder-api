<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubscriptionStatusService
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository
    ) {}

    /**
     * Récupère le statut d'un abonnement (ACTIVE, SUSPENDED, CANCELLED)
     */
    public function getStatus(string $subscriptionId): string
    {
        $subscription = $this->findSubscriptionOrFail($subscriptionId);

        return $subscription->getStatus();
    }

    /**
     * Met à jour le statut d'un abonnement
     */
    public function updateStatus(string $subscriptionId, string $status): Subscription
    {
        $subscription = $this->findSubscriptionOrFail($subscriptionId);

        // Valide le statut
        if (!in_array($status, [
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_SUSPENDED,
            Subscription::STATUS_CANCELLED
        ])) {
            throw new \InvalidArgumentException("Statut invalide: $status");
        }

        $subscription->setStatus($status);
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->subscriptionRepository->save($subscription, true);

        return $subscription;
    }

    /**
     * Recherche un abonnement ou lève une exception
     */
    private function findSubscriptionOrFail(string $subscriptionId): Subscription
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new NotFoundHttpException("Abonnement introuvable.");
        }

        return $subscription;
    }
}