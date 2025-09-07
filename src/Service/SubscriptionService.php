<?php

namespace App\Service;

use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\PlanRepository;
use App\Repository\SubscriptionRepository;
use RuntimeException;
use Stripe\Subscription as StripeSubscription;

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
        // Annuler tous les autres abonnements actifs avant de créer le nouveau
        $this->cancelActiveSubscriptions($user);

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

    /**
     * Crée un abonnement local à partir d'un abonnement Stripe
     */
    public function createFromStripeSubscription(User $user, StripeSubscription $stripeSubscription): Subscription
    {
        // Vérifier si l'abonnement existe déjà
        $existingSubscription = $this->subscriptionRepository->findOneBy([
            'stripeSubscriptionId' => $stripeSubscription->id,
        ]);

        if ($existingSubscription) {
            return $existingSubscription;
        }

        // Récupérer le plan correspondant au price_id Stripe
        $priceId = $stripeSubscription->items->data[0]->price->id;
        $plan = $this->planRepository->findOneBy(['stripePriceId' => $priceId]);

        if (! $plan) {
            throw new RuntimeException("Plan introuvable pour le price_id Stripe: {$priceId}");
        }

        // Annuler tous les autres abonnements actifs avant de créer le nouveau
        $this->cancelActiveSubscriptions($user);

        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setPlan($plan);
        $subscription->setStripeSubscriptionId($stripeSubscription->id);

        $startDate = \DateTime::createFromFormat('U', (string) ($stripeSubscription->current_period_start ?? time()));
        $endDate = \DateTime::createFromFormat('U', (string) ($stripeSubscription->current_period_end ?? time()));

        $subscription->setStartDate($startDate ?: new \DateTime());
        $subscription->setEndDate($endDate ?: new \DateTime());
        $subscription->setStatus($this->mapStripeStatusToLocal($stripeSubscription->status));

        $this->subscriptionRepository->save($subscription, true);

        return $subscription;
    }

    /**
     * Met à jour un abonnement local à partir d'un abonnement Stripe
     */
    public function updateFromStripeSubscription(Subscription $subscription, StripeSubscription $stripeSubscription): Subscription
    {
        // Mettre à jour le plan si nécessaire
        $priceId = $stripeSubscription->items->data[0]->price->id;
        $plan = $this->planRepository->findOneBy(['stripePriceId' => $priceId]);

        if ($plan && $plan !== $subscription->getPlan()) {
            $subscription->setPlan($plan);
        }

        $startDate = \DateTime::createFromFormat('U', (string) ($stripeSubscription->current_period_start ?? time()));
        $endDate = \DateTime::createFromFormat('U', (string) ($stripeSubscription->current_period_end ?? time()));

        $subscription->setStartDate($startDate ?: new \DateTime());
        $subscription->setEndDate($endDate ?: new \DateTime());
        $subscription->setStatus($this->mapStripeStatusToLocal($stripeSubscription->status));
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->subscriptionRepository->save($subscription, true);

        return $subscription;
    }

    /**
     * Mappe un statut Stripe vers un statut local
     */
    private function mapStripeStatusToLocal(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active', 'trialing' => Subscription::STATUS_ACTIVE,
            'past_due', 'unpaid' => Subscription::STATUS_SUSPENDED,
            'canceled', 'incomplete_expired' => Subscription::STATUS_CANCELLED,
            default => Subscription::STATUS_SUSPENDED,
        };
    }
}
