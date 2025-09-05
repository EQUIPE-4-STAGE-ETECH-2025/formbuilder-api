<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StripePaymentFailureService
{
    private const MAX_RETRY_ATTEMPTS = 3;
    private const GRACE_PERIOD_DAYS = 7;
    private const SUSPENSION_DELAY_DAYS = 14;

    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly UserRepository $userRepository,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * Gère l'échec de paiement d'une facture
     */
    public function handlePaymentFailure(string $invoiceId, string $failureCode = null): array
    {
        try {
            $invoice = Invoice::retrieve($invoiceId);

            if (! $invoice->subscription) {
                throw new \RuntimeException('Facture sans abonnement associé');
            }

            $subscription = $this->subscriptionRepository->findOneBy([
                'stripeSubscriptionId' => $invoice->subscription,
            ]);

            if (! $subscription) {
                throw new \RuntimeException("Abonnement local introuvable: {$invoice->subscription}");
            }

            $user = $subscription->getUser();
            $attemptCount = $this->getRetryAttemptCount($invoice);

            $this->logger->info('Traitement d\'échec de paiement', [
                'invoice_id' => $invoiceId,
                'subscription_id' => $subscription->getId(),
                'user_id' => $user->getId(),
                'attempt_count' => $attemptCount,
                'failure_code' => $failureCode,
            ]);

            // Logique de dunning selon le nombre de tentatives
            $result = match (true) {
                $attemptCount === 1 => $this->handleFirstFailure($user, $invoice, $subscription),
                $attemptCount === 2 => $this->handleSecondFailure($user, $invoice, $subscription),
                $attemptCount === 3 => $this->handleThirdFailure($user, $invoice, $subscription),
                $attemptCount >= self::MAX_RETRY_ATTEMPTS => $this->handleMaxAttemptsReached($user, $invoice, $subscription),
                default => $this->handleSubsequentFailures($user, $invoice, $subscription, $attemptCount)
            };

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du traitement de l\'échec de paiement', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Premier échec de paiement - notification simple
     */
    private function handleFirstFailure(User $user, Invoice $invoice, Subscription $subscription): array
    {
        // Envoyer une notification immédiate
        $this->emailService->sendPaymentFailedNotification($user, $invoice, 1);

        // Programmer une nouvelle tentative dans 3 jours
        $this->scheduleRetryAttempt($invoice, 3);

        $this->logger->info('Premier échec de paiement traité', [
            'user_id' => $user->getId(),
            'invoice_id' => $invoice->id,
        ]);

        return [
            'status' => 'first_failure_handled',
            'action' => 'notification_sent',
            'retry_scheduled' => true,
            'retry_days' => 3,
        ];
    }

    /**
     * Deuxième échec de paiement - notification plus urgente
     */
    private function handleSecondFailure(User $user, Invoice $invoice, Subscription $subscription): array
    {
        // Envoyer une notification plus urgente
        $this->emailService->sendPaymentFailedNotification($user, $invoice, 2);

        // Programmer une tentative dans 5 jours
        $this->scheduleRetryAttempt($invoice, 5);

        $this->logger->info('Deuxième échec de paiement traité', [
            'user_id' => $user->getId(),
            'invoice_id' => $invoice->id,
        ]);

        return [
            'status' => 'second_failure_handled',
            'action' => 'urgent_notification_sent',
            'retry_scheduled' => true,
            'retry_days' => 5,
        ];
    }

    /**
     * Troisième échec de paiement - avertissement de suspension
     */
    private function handleThirdFailure(User $user, Invoice $invoice, Subscription $subscription): array
    {
        // Envoyer un avertissement de suspension imminente
        $this->emailService->sendSuspensionWarningNotification($user, $invoice);

        // Programmer une dernière tentative dans 7 jours
        $this->scheduleRetryAttempt($invoice, 7);

        $this->logger->warning('Troisième échec de paiement - suspension imminente', [
            'user_id' => $user->getId(),
            'invoice_id' => $invoice->id,
        ]);

        return [
            'status' => 'third_failure_handled',
            'action' => 'suspension_warning_sent',
            'retry_scheduled' => true,
            'retry_days' => 7,
        ];
    }

    /**
     * Limite de tentatives atteinte - suspension
     */
    private function handleMaxAttemptsReached(User $user, Invoice $invoice, Subscription $subscription): array
    {
        // Suspendre l'abonnement
        $this->suspendSubscription($subscription);

        // Envoyer une notification de suspension
        $this->emailService->sendSubscriptionSuspendedNotification($user, $subscription);

        // Programmer une rétrogradation vers le plan gratuit après la période de grâce
        $this->scheduleDowngradeToFree($subscription, self::GRACE_PERIOD_DAYS);

        $this->logger->warning('Abonnement suspendu après échecs multiples', [
            'user_id' => $user->getId(),
            'subscription_id' => $subscription->getId(),
            'invoice_id' => $invoice->id,
        ]);

        return [
            'status' => 'subscription_suspended',
            'action' => 'suspended_due_to_payment_failures',
            'grace_period_days' => self::GRACE_PERIOD_DAYS,
        ];
    }

    /**
     * Échecs supplémentaires après suspension
     */
    private function handleSubsequentFailures(User $user, Invoice $invoice, Subscription $subscription, int $attemptCount): array
    {
        // Si déjà suspendu, ne rien faire de plus
        if ($subscription->getStatus() === Subscription::STATUS_SUSPENDED) {
            return [
                'status' => 'already_suspended',
                'action' => 'no_action_taken',
                'attempt_count' => $attemptCount,
            ];
        }

        // Sinon, traiter comme un échec max
        return $this->handleMaxAttemptsReached($user, $invoice, $subscription);
    }

    /**
     * Relance manuelle du paiement d'une facture
     */
    public function retryPayment(string $invoiceId): array
    {
        try {
            $invoice = Invoice::retrieve($invoiceId);

            // Vérifier si la facture peut être payée
            if ($invoice->status !== 'open') {
                throw new \RuntimeException('La facture n\'est pas dans un état permettant le paiement');
            }

            // Relancer le paiement
            $invoice = $invoice->pay();

            $this->logger->info('Paiement relancé manuellement', [
                'invoice_id' => $invoiceId,
                'status' => $invoice->status,
            ]);

            return [
                'status' => 'payment_retried',
                'invoice_status' => $invoice->status,
                'payment_successful' => $invoice->status === 'paid',
            ];
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la relance manuelle du paiement', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'retry_failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Suspend un abonnement
     */
    private function suspendSubscription(Subscription $subscription): void
    {
        $subscription->setStatus(Subscription::STATUS_SUSPENDED);
        $subscription->setUpdatedAt(new \DateTimeImmutable());
        $this->subscriptionRepository->save($subscription, true);

        // Suspendre également l'abonnement côté Stripe
        try {
            \Stripe\Subscription::update($subscription->getStripeSubscriptionId(), [
                'pause_collection' => [
                    'behavior' => 'keep_as_draft',
                ],
                'metadata' => [
                    'suspended_reason' => 'payment_failure',
                    'suspended_at' => time(),
                ],
            ]);
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la suspension côté Stripe', [
                'subscription_id' => $subscription->getId(),
                'stripe_subscription_id' => $subscription->getStripeSubscriptionId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Programme une nouvelle tentative de paiement
     */
    private function scheduleRetryAttempt(Invoice $invoice, int $days): void
    {
        try {
            // Mettre à jour la facture avec une nouvelle date d'échéance
            Invoice::update($invoice->id, [
                'days_until_due' => $days,
                'metadata' => array_merge(
                    $invoice->metadata->toArray() ?? [],
                    [
                        'retry_scheduled' => 'true',
                        'retry_scheduled_at' => time(),
                        'retry_days' => $days,
                    ]
                ),
            ]);

            $this->logger->info('Nouvelle tentative programmée', [
                'invoice_id' => $invoice->id,
                'retry_days' => $days,
            ]);
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la programmation de la nouvelle tentative', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Programme une rétrogradation vers le plan gratuit
     */
    private function scheduleDowngradeToFree(Subscription $subscription, int $gracePeriodDays): void
    {
        // Cette méthode devrait être implémentée avec un système de jobs/queue
        // Pour l'instant, on se contente de logger l'intention
        $this->logger->info('Rétrogradation programmée vers le plan gratuit', [
            'subscription_id' => $subscription->getId(),
            'grace_period_days' => $gracePeriodDays,
            'scheduled_for' => (new \DateTime())->add(new \DateInterval("P{$gracePeriodDays}D"))->format('Y-m-d H:i:s'),
        ]);

        // TODO: Implémenter avec Symfony Messenger ou un autre système de queue
        // $this->messageBus->dispatch(new DowngradeToFreeMessage($subscription->getId(), $gracePeriodDays));
    }

    /**
     * Récupère le nombre de tentatives de paiement pour une facture
     */
    private function getRetryAttemptCount(Invoice $invoice): int
    {
        try {
            // Récupérer les tentatives de paiement via les PaymentIntents
            if ($invoice->payment_intent) {
                $paymentIntent = PaymentIntent::retrieve($invoice->payment_intent);

                return count($paymentIntent->charges->data);
            }

            // Fallback: regarder dans les metadata de la facture
            $metadata = $invoice->metadata->toArray() ?? [];

            return (int) ($metadata['attempt_count'] ?? 1);
        } catch (\Exception $e) {
            $this->logger->warning('Impossible de déterminer le nombre de tentatives', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return 1;
        }
    }

    /**
     * Réactive un abonnement suspendu après paiement réussi
     */
    public function reactivateSubscription(string $subscriptionId): array
    {
        try {
            $subscription = $this->subscriptionRepository->findOneBy([
                'stripeSubscriptionId' => $subscriptionId,
            ]);

            if (! $subscription) {
                throw new \RuntimeException("Abonnement local introuvable: {$subscriptionId}");
            }

            if ($subscription->getStatus() !== Subscription::STATUS_SUSPENDED) {
                return [
                    'status' => 'not_suspended',
                    'message' => 'L\'abonnement n\'est pas suspendu',
                ];
            }

            // Réactiver l'abonnement local
            $subscription->setStatus(Subscription::STATUS_ACTIVE);
            $subscription->setUpdatedAt(new \DateTimeImmutable());
            $this->subscriptionRepository->save($subscription, true);

            // Réactiver l'abonnement côté Stripe
            \Stripe\Subscription::update($subscriptionId, [
                'pause_collection' => null,
                'metadata' => [
                    'reactivated_at' => time(),
                    'reactivated_reason' => 'payment_successful',
                ],
            ]);

            // Envoyer une notification de réactivation
            $this->emailService->sendSubscriptionReactivatedNotification($subscription->getUser());

            $this->logger->info('Abonnement réactivé avec succès', [
                'subscription_id' => $subscription->getId(),
                'stripe_subscription_id' => $subscriptionId,
            ]);

            return [
                'status' => 'reactivated',
                'message' => 'Abonnement réactivé avec succès',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la réactivation de l\'abonnement', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
