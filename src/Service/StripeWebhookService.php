<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StripeWebhookService
{
    public function __construct(
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private readonly string $webhookSecret,
        private readonly UserRepository $userRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionService $subscriptionService,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Vérifie et traite un webhook Stripe
     * @return array<string, mixed>
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        try {
            // Vérifier la signature du webhook
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        } catch (SignatureVerificationException $e) {
            $this->logger->error('Signature webhook Stripe invalide', [
                'error' => $e->getMessage(),
            ]);

            throw new \InvalidArgumentException('Signature webhook invalide');
        }

        $this->logger->info('Webhook Stripe reçu', [
            'event_id' => $event->id,
            'event_type' => $event->type,
            'created' => $event->created,
        ]);

        try {
            $result = $this->processEvent($event);

            $this->logger->info('Webhook Stripe traité avec succès', [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'result' => $result,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du traitement du webhook Stripe', [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Traite un événement Stripe selon son type
     * @return array<string, mixed>
     */
    private function processEvent(Event $event): array
    {
        switch ($event->type) {
            case 'checkout.session.completed':
                return $this->handleCheckoutSessionCompleted($event);

            case 'customer.subscription.created':
                return $this->handleSubscriptionCreated($event);

            case 'customer.subscription.updated':
                return $this->handleSubscriptionUpdated($event);

            case 'customer.subscription.deleted':
                return $this->handleSubscriptionDeleted($event);

            case 'customer.subscription.trial_will_end':
                return $this->handleTrialWillEnd($event);

            case 'invoice.created':
                return $this->handleInvoiceCreated($event);

            case 'invoice.paid':
                return $this->handleInvoicePaid($event);

            case 'invoice.payment_failed':
                return $this->handleInvoicePaymentFailed($event);

            case 'invoice.payment_succeeded':
                return $this->handleInvoicePaymentSucceeded($event);

            case 'payment_method.attached':
                return $this->handlePaymentMethodAttached($event);

            case 'payment_method.detached':
                return $this->handlePaymentMethodDetached($event);

            case 'customer.created':
                return $this->handleCustomerCreated($event);

            case 'customer.updated':
                return $this->handleCustomerUpdated($event);

            case 'customer.deleted':
                return $this->handleCustomerDeleted($event);

            default:
                $this->logger->info('Type d\'événement webhook non géré', [
                    'event_type' => $event->type,
                    'event_id' => $event->id,
                ]);

                return ['status' => 'ignored', 'message' => 'Type d\'événement non géré'];
        }
    }

    /**
     * Gère l'événement checkout.session.completed
     */
    /** @return array<string, mixed> */
    private function handleCheckoutSessionCompleted(Event $event): array
    {
        $session = $event->data->object;

        // Récupérer l'utilisateur par l'ID dans les metadata
        $userId = $session->metadata['user_id'] ?? null;
        if (! $userId) {
            throw new \RuntimeException('user_id manquant dans les metadata de la session');
        }

        $user = $this->userRepository->find($userId);
        if (! $user) {
            throw new \RuntimeException("Utilisateur introuvable: {$userId}");
        }

        // Si c'est un abonnement, créer l'abonnement local
        if (isset($session->mode) && $session->mode === 'subscription' && isset($session->subscription)) {
            $stripeSubscription = \Stripe\Subscription::retrieve($session->subscription);

            // Créer l'abonnement local
            $this->subscriptionService->createFromStripeSubscription($user, $stripeSubscription);

            // Envoyer un email de bienvenue
            $this->emailService->sendSubscriptionConfirmation($user, $stripeSubscription);
        }

        return [
            'status' => 'success',
            'message' => 'Session de checkout traitée',
            'user_id' => $userId,
            'session_id' => $session->id,
        ];
    }

    /**
     * Gère l'événement customer.subscription.created
     */
    /** @return array<string, mixed> */
    private function handleSubscriptionCreated(Event $event): array
    {
        $stripeSubscription = $event->data->object;

        $customerId = $stripeSubscription->customer ?? '';
        $user = $this->getUserByStripeCustomerId($customerId);
        if (! $user) {
            throw new \RuntimeException("Utilisateur introuvable pour customer: {$customerId}");
        }

        // Créer l'abonnement local s'il n'existe pas déjà
        $existingSubscription = $this->subscriptionRepository->findOneBy([
            'stripeSubscriptionId' => $stripeSubscription->id,
        ]);

        if (! $existingSubscription && $stripeSubscription instanceof \Stripe\Subscription) {
            $this->subscriptionService->createFromStripeSubscription($user, $stripeSubscription);
        }

        return [
            'status' => 'success',
            'message' => 'Abonnement créé',
            'subscription_id' => $stripeSubscription->id,
        ];
    }

    /**
     * Gère l'événement customer.subscription.updated
     * @return array<string, mixed>
     */
    private function handleSubscriptionUpdated(Event $event): array
    {
        $stripeSubscription = $event->data->object;

        $subscription = $this->subscriptionRepository->findOneBy([
            'stripeSubscriptionId' => $stripeSubscription->id,
        ]);

        if ($subscription && $stripeSubscription instanceof \Stripe\Subscription) {
            $this->subscriptionService->updateFromStripeSubscription($subscription, $stripeSubscription);
        }

        return [
            'status' => 'success',
            'message' => 'Abonnement mis à jour',
            'subscription_id' => $stripeSubscription->id,
        ];
    }

    /**
     * Gère l'événement customer.subscription.deleted
     * @return array<string, mixed>
     */
    private function handleSubscriptionDeleted(Event $event): array
    {
        $stripeSubscription = $event->data->object;

        $subscription = $this->subscriptionRepository->findOneBy([
            'stripeSubscriptionId' => $stripeSubscription->id,
        ]);

        if ($subscription) {
            $subscription->setStatus(Subscription::STATUS_CANCELLED);
            $subscription->setUpdatedAt(new \DateTimeImmutable());
            $this->subscriptionRepository->save($subscription, true);

            // Envoyer un email de confirmation d'annulation
            $user = $subscription->getUser();
            if ($user) {
                $this->emailService->sendSubscriptionCancellation($user);
            }
        }

        return [
            'status' => 'success',
            'message' => 'Abonnement annulé',
            'subscription_id' => $stripeSubscription->id,
        ];
    }

    /**
     * Gère l'événement customer.subscription.trial_will_end
     * @return array<string, mixed>
     */
    private function handleTrialWillEnd(Event $event): array
    {
        $stripeSubscription = $event->data->object;

        $customerId = $stripeSubscription->customer ?? null;
        if (! $customerId) {
            throw new \RuntimeException('Customer ID manquant dans l\'abonnement');
        }

        $user = $this->getUserByStripeCustomerId($customerId);
        if ($user && $stripeSubscription instanceof \Stripe\Subscription) {
            // Envoyer un email de rappel de fin d'essai
            $this->emailService->sendTrialEndingNotification($user, $stripeSubscription);
        }

        return [
            'status' => 'success',
            'message' => 'Notification de fin d\'essai envoyée',
            'subscription_id' => $stripeSubscription->id,
        ];
    }

    /**
     * Gère l'événement invoice.paid
     * @return array<string, mixed>
     */
    private function handleInvoicePaid(Event $event): array
    {
        $invoice = $event->data->object;

        // Réactiver l'abonnement si nécessaire
        $subscriptionId = $invoice->subscription ?? null;
        if ($subscriptionId) {
            $subscription = $this->subscriptionRepository->findOneBy([
                'stripeSubscriptionId' => $subscriptionId,
            ]);

            if ($subscription && $subscription->getStatus() === Subscription::STATUS_SUSPENDED) {
                $subscription->setStatus(Subscription::STATUS_ACTIVE);
                $subscription->setUpdatedAt(new \DateTimeImmutable());
                $this->subscriptionRepository->save($subscription, true);
            }
        }

        return [
            'status' => 'success',
            'message' => 'Facture payée traitée',
            'invoice_id' => $invoice->id,
        ];
    }

    /**
     * Gère l'événement invoice.payment_failed
     * @return array<string, mixed>
     */
    private function handleInvoicePaymentFailed(Event $event): array
    {
        $invoice = $event->data->object;

        $customerId = $invoice->customer ?? null;
        $subscriptionId = $invoice->subscription ?? null;

        if (! $customerId) {
            throw new \RuntimeException('Customer ID manquant dans la facture');
        }

        $user = $this->getUserByStripeCustomerId($customerId);
        if ($user && $subscriptionId) {
            // Suspendre l'abonnement après plusieurs échecs
            $subscription = $this->subscriptionRepository->findOneBy([
                'stripeSubscriptionId' => $subscriptionId,
            ]);

            if ($subscription && $invoice instanceof \Stripe\Invoice) {
                // Logique de suspension après plusieurs échecs (à implémenter selon vos besoins)
                $this->emailService->sendPaymentFailedNotification($user, $invoice);
            }
        }

        return [
            'status' => 'success',
            'message' => 'Échec de paiement traité',
            'invoice_id' => $invoice->id,
        ];
    }

    /**
     * Gère l'événement invoice.payment_succeeded
     * @return array<string, mixed>
     */
    private function handleInvoicePaymentSucceeded(Event $event): array
    {
        return $this->handleInvoicePaid($event);
    }

    /**
     * Gère l'événement invoice.created
     * @return array<string, mixed>
     */
    private function handleInvoiceCreated(Event $event): array
    {
        // Généralement pas d'action spécifique nécessaire
        return [
            'status' => 'success',
            'message' => 'Facture créée',
            'invoice_id' => $event->data->object->id,
        ];
    }

    /**
     * Gère l'événement payment_method.attached
     * @return array<string, mixed>
     */
    private function handlePaymentMethodAttached(Event $event): array
    {
        return [
            'status' => 'success',
            'message' => 'Méthode de paiement attachée',
            'payment_method_id' => $event->data->object->id,
        ];
    }

    /**
     * Gère l'événement payment_method.detached
     * @return array<string, mixed>
     */
    private function handlePaymentMethodDetached(Event $event): array
    {
        return [
            'status' => 'success',
            'message' => 'Méthode de paiement détachée',
            'payment_method_id' => $event->data->object->id,
        ];
    }

    /**
     * Gère l'événement customer.created
     * @return array<string, mixed>
     */
    private function handleCustomerCreated(Event $event): array
    {
        return [
            'status' => 'success',
            'message' => 'Customer créé',
            'customer_id' => $event->data->object->id,
        ];
    }

    /**
     * Gère l'événement customer.updated
     * @return array<string, mixed>
     */
    private function handleCustomerUpdated(Event $event): array
    {
        return [
            'status' => 'success',
            'message' => 'Customer mis à jour',
            'customer_id' => $event->data->object->id,
        ];
    }

    /**
     * Gère l'événement customer.deleted
     * @return array<string, mixed>
     */
    private function handleCustomerDeleted(Event $event): array
    {
        return [
            'status' => 'success',
            'message' => 'Customer supprimé',
            'customer_id' => $event->data->object->id,
        ];
    }

    /**
     * Récupère un utilisateur par son ID customer Stripe
     */
    private function getUserByStripeCustomerId(string $stripeCustomerId): ?User
    {
        return $this->userRepository->findOneBy([
            'stripeCustomerId' => $stripeCustomerId,
        ]);
    }
}
