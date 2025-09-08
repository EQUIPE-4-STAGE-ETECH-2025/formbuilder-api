<?php

namespace App\Service;

use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\PlanRepository;
use App\Repository\SubscriptionRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Stripe\Subscription as StripeSubscription;

class SubscriptionService
{
    public function __construct(
        private readonly PlanRepository $planRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly LoggerInterface $logger
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

        // Les plans gratuits n'ont pas de date d'expiration
        if (! $plan->isFree()) {
            $subscription->setEndDate(new \DateTime('+1 month'));
        }

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


        // Récupérer l'abonnement complet depuis Stripe si nécessaire
        try {
            $stripeSubscription = $this->ensureCompleteStripeSubscription($stripeSubscription);
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la récupération des données Stripe: " . $e->getMessage());
            // Ne pas lancer l'exception, continuer avec les données disponibles
        }

        // Récupérer le plan correspondant au price_id Stripe
        $priceId = null;
        if (isset($stripeSubscription->items) && isset($stripeSubscription->items->data[0]->price->id)) {
            $priceId = $stripeSubscription->items->data[0]->price->id;
        } else {
            throw new RuntimeException("Impossible de récupérer le price_id depuis l'abonnement Stripe: {$stripeSubscription->id}");
        }

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

        // Conversion correcte des timestamps Stripe

        $startDate = null;
        $endDate = null;

        // Essayer d'abord current_period_start/end
        if (isset($stripeSubscription['current_period_start'])) {
            $startDate = \DateTime::createFromFormat('U', (string) $stripeSubscription['current_period_start']);
        }
        if (isset($stripeSubscription['current_period_end'])) {
            $endDate = \DateTime::createFromFormat('U', (string) $stripeSubscription['current_period_end']);
        }

        // Si les dates de période ne sont pas disponibles, utiliser billing_cycle_anchor
        if (! $startDate && $stripeSubscription->billing_cycle_anchor) {
            $startDate = \DateTime::createFromFormat('U', (string) $stripeSubscription->billing_cycle_anchor);
        }

        // Si endDate n'est pas disponible, calculer basé sur l'intervalle
        if (! $endDate && $startDate) {
            $interval = $this->getBillingInterval($stripeSubscription);
            $endDate = clone $startDate;
            $endDate->modify($interval);
        }


        // Validation des dates avec fallback final
        if (! $startDate) {
            $startDate = new \DateTime();
        }

        if (! $endDate) {
            $endDate = new \DateTime('+1 month');
        }

        $subscription->setStartDate($startDate);
        $subscription->setEndDate($endDate);
        $subscription->setStatus($this->mapStripeStatusToLocal($stripeSubscription->status));

        $this->subscriptionRepository->save($subscription, true);

        return $subscription;
    }

    /**
     * Met à jour un abonnement local à partir d'un abonnement Stripe
     */
    public function updateFromStripeSubscription(Subscription $subscription, StripeSubscription $stripeSubscription): Subscription
    {
        // Récupérer l'abonnement complet depuis Stripe si nécessaire
        $stripeSubscription = $this->ensureCompleteStripeSubscription($stripeSubscription);

        // Mettre à jour le plan si nécessaire
        $priceId = $stripeSubscription->items->data[0]->price->id;
        $plan = $this->planRepository->findOneBy(['stripePriceId' => $priceId]);

        if ($plan && $plan !== $subscription->getPlan()) {
            $subscription->setPlan($plan);
        }

        // Conversion correcte des timestamps Stripe
        $startDate = \DateTime::createFromFormat('U', (string) $stripeSubscription['current_period_start']);
        $endDate = \DateTime::createFromFormat('U', (string) $stripeSubscription['current_period_end']);

        // Validation des dates
        if (! $startDate || ! $endDate) {
            throw new RuntimeException("Impossible de parser les dates de l'abonnement Stripe: {$stripeSubscription->id}");
        }

        $subscription->setStartDate($startDate);
        $subscription->setEndDate($endDate);
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

    /**
     * Récupère l'intervalle de facturation depuis l'abonnement Stripe
     */
    private function getBillingInterval(StripeSubscription $stripeSubscription): string
    {
        if (isset($stripeSubscription->items) &&
            isset($stripeSubscription->items->data[0]->price->recurring->interval)) {

            $interval = $stripeSubscription->items->data[0]->price->recurring->interval;
            $intervalCount = $stripeSubscription->items->data[0]->price->recurring->interval_count ?? 1;

            return match ($interval) {
                'day' => "+{$intervalCount} day",
                'week' => "+{$intervalCount} week",
                'month' => "+{$intervalCount} month",
                'year' => "+{$intervalCount} year",
                default => "+1 month",
            };
        }

        return "+1 month";
    }

    /**
     * Récupère un abonnement complet depuis Stripe si nécessaire
     */
    private function ensureCompleteStripeSubscription(StripeSubscription $stripeSubscription): StripeSubscription
    {

        // Vérifier si les champs essentiels sont présents
        if (! isset($stripeSubscription['current_period_end']) ||
            ! isset($stripeSubscription['current_period_start']) ||
            ! isset($stripeSubscription->items) ||
            ! isset($stripeSubscription->items->data[0]->price->id)) {

            try {
                $completeSubscription = \Stripe\Subscription::retrieve($stripeSubscription->id);

                return $completeSubscription;
            } catch (\Exception $e) {
                $this->logger->error("Erreur lors de la récupération depuis Stripe: " . $e->getMessage());

                throw $e;
            }
        }

        return $stripeSubscription;
    }
}
