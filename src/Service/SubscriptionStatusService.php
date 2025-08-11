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
     * Récupère le statut d'un abonnement
     */
    public function getStatus(string $subscriptionId): bool
    {
        $subscription = $this->findSubscriptionOrFail($subscriptionId);

        return $subscription->isActive();
    }


    /**
     * Met à jour le statut d'un abonnement
     */
    public function updateStatus(string $subscriptionId, bool $isActive): Subscription
{
    $subscription = $this->findSubscriptionOrFail($subscriptionId);

    $subscription->setIsActive($isActive);
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
