<?php

namespace App\Service;

use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\PlanRepository;
use App\Repository\SubscriptionRepository;
use RuntimeException;

class SubscriptionService
{
    public function __construct(
        private readonly PlanRepository $planRepository,
        private readonly SubscriptionRepository $subscriptionRepository
    ) {
    }

    /**
     * Assigne un plan Free par défaut à un utilisateur
     */
    public function assignDefaultFreePlan(User $user): Subscription
    {
        // Vérifier si l'utilisateur a déjà une subscription active
        $existingSubscription = $this->subscriptionRepository->findOneBy([
            'user' => $user,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        if ($existingSubscription) {
            return $existingSubscription;
        }

        // Récupérer le plan Free
        $freePlan = $this->planRepository->findDefaultFreePlan();
        if (! $freePlan) {
            throw new RuntimeException('Plan Free par défaut introuvable. Veuillez contacter le support.');
        }

        return $this->createSubscription($user, $freePlan);
    }

    /**
     * Crée une nouvelle subscription pour un utilisateur et un plan donnés
     */
    public function createSubscription(User $user, Plan $plan): Subscription
    {
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setPlan($plan);
        $subscription->setStripeSubscriptionId('sub_free_' . uniqid());
        $subscription->setStartDate(new \DateTime());
        $subscription->setEndDate(new \DateTime('+1 year'));
        $subscription->setStatus(Subscription::STATUS_ACTIVE);

        $this->subscriptionRepository->save($subscription, true);

        return $subscription;
    }

    /**
     * Vérifie si un utilisateur a une subscription active
     */
    public function hasActiveSubscription(User $user): bool
    {
        $subscription = $this->subscriptionRepository->findOneBy([
            'user' => $user,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        return $subscription !== null;
    }

    /**
     * Récupère la subscription active d'un utilisateur
     */
    public function getActiveSubscription(User $user): ?Subscription
    {
        return $this->subscriptionRepository->findOneBy([
            'user' => $user,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    /**
     * Annule toutes les subscriptions actives d'un utilisateur
     */
    public function cancelActiveSubscriptions(User $user): void
    {
        $activeSubscriptions = $this->subscriptionRepository->findBy([
            'user' => $user,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        foreach ($activeSubscriptions as $subscription) {
            $subscription->setStatus(Subscription::STATUS_CANCELLED);
            $subscription->setUpdatedAt(new \DateTimeImmutable());
            $this->subscriptionRepository->save($subscription, false);
        }

        if (! empty($activeSubscriptions)) {
            $this->subscriptionRepository->flush();
        }
    }

    /**
     * Change le plan d'un utilisateur
     */
    public function changePlan(User $user, Plan $newPlan): Subscription
    {
        // Annuler les subscriptions actives
        $this->cancelActiveSubscriptions($user);

        // Créer une nouvelle subscription avec le nouveau plan
        return $this->createSubscription($user, $newPlan);
    }
}
