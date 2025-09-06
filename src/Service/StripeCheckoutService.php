<?php

namespace App\Service;

use App\Dto\CheckoutSessionDto;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StripeCheckoutService
{
    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey,
        private readonly StripeService $stripeService,
        private readonly LoggerInterface $logger
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * Crée une session de checkout Stripe
     */
    public function createCheckoutSession(User $user, CheckoutSessionDto $checkoutDto): Session
    {
        try {
            // Créer ou récupérer le customer Stripe
            $customer = $this->stripeService->createOrGetCustomer($user);

            $sessionData = [
                'payment_method_types' => ['card'],
                'mode' => $checkoutDto->mode,
                'customer' => $customer->id,
                'success_url' => $checkoutDto->successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $checkoutDto->cancelUrl,
                'metadata' => array_merge(
                    ['user_id' => (string) $user->getId()],
                    array_filter($checkoutDto->metadata, fn ($value) => $value !== null)
                ),
            ];

            // Configuration spécifique au mode abonnement
            if ($checkoutDto->mode === 'subscription') {
                $sessionData['line_items'] = [
                    [
                        'price' => $checkoutDto->priceId,
                        'quantity' => $checkoutDto->quantity,
                    ],
                ];

                // Ajouter une période d'essai si spécifiée
                if ($checkoutDto->trialPeriodDays !== null) {
                    $sessionData['subscription_data'] = [
                        'trial_period_days' => $checkoutDto->trialPeriodDays,
                        'metadata' => array_filter($checkoutDto->metadata, fn ($value) => $value !== null),
                    ];
                }
            } else {
                // Mode paiement unique
                $sessionData['line_items'] = [
                    [
                        'price' => $checkoutDto->priceId,
                        'quantity' => $checkoutDto->quantity,
                    ],
                ];
            }

            // Options additionnelles
            if ($checkoutDto->allowPromotionCodes) {
                $sessionData['allow_promotion_codes'] = true;
            }

            // Configuration de la facturation automatique
            $sessionData['automatic_tax'] = ['enabled' => true];

            // Configuration des méthodes de paiement (supprimé car null n'est pas accepté)

            $session = Session::create($sessionData);

            $this->logger->info('Session de checkout Stripe créée avec succès', [
                'session_id' => $session->id,
                'user_id' => $user->getId(),
                'price_id' => $checkoutDto->priceId,
                'mode' => $checkoutDto->mode,
            ]);

            return $session;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la création de la session de checkout Stripe', [
                'user_id' => $user->getId(),
                'price_id' => $checkoutDto->priceId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de créer la session de checkout: ' . $e->getMessage());
        }
    }

    /**
     * Récupère une session de checkout Stripe
     */
    public function getCheckoutSession(string $sessionId): Session
    {
        try {
            $session = Session::retrieve([
                'id' => $sessionId,
                'expand' => ['line_items', 'customer', 'subscription'],
            ]);

            return $session;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la récupération de la session de checkout', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de récupérer la session de checkout: ' . $e->getMessage());
        }
    }

    /**
     * Vérifie si une session de checkout est complète
     */
    public function isSessionCompleted(string $sessionId): bool
    {
        try {
            $session = $this->getCheckoutSession($sessionId);

            return $session->payment_status === 'paid';
        } catch (\Exception $e) {
            $this->logger->warning('Erreur lors de la vérification du statut de la session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Crée une session de checkout pour un changement de plan
     */
    public function createUpgradeCheckoutSession(
        User $user,
        string $newPriceId,
        string $currentSubscriptionId,
        CheckoutSessionDto $checkoutDto
    ): Session {
        try {
            $customer = $this->stripeService->createOrGetCustomer($user);

            $sessionData = [
                'payment_method_types' => ['card'],
                'mode' => 'subscription',
                'customer' => $customer->id,
                'success_url' => $checkoutDto->successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $checkoutDto->cancelUrl,
                'line_items' => [
                    [
                        'price' => $newPriceId,
                        'quantity' => $checkoutDto->quantity,
                    ],
                ],
                'subscription_data' => [
                    'metadata' => array_filter(array_merge(
                        [
                            'user_id' => (string) $user->getId(),
                            'upgrade_from_subscription' => $currentSubscriptionId,
                        ],
                        $checkoutDto->metadata
                    ), fn ($value) => $value !== null),
                ],
                'metadata' => [
                    'user_id' => (string) $user->getId(),
                    'upgrade_from_subscription' => $currentSubscriptionId,
                ],
            ];

            if ($checkoutDto->allowPromotionCodes) {
                $sessionData['allow_promotion_codes'] = true;
            }

            $session = Session::create($sessionData);

            $this->logger->info('Session de checkout pour upgrade créée avec succès', [
                'session_id' => $session->id,
                'user_id' => $user->getId(),
                'new_price_id' => $newPriceId,
                'current_subscription_id' => $currentSubscriptionId,
            ]);

            return $session;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la création de la session de checkout pour upgrade', [
                'user_id' => $user->getId(),
                'new_price_id' => $newPriceId,
                'current_subscription_id' => $currentSubscriptionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de créer la session de checkout pour l\'upgrade: ' . $e->getMessage());
        }
    }

    /**
     * Expire une session de checkout
     */
    public function expireCheckoutSession(string $sessionId): Session
    {
        try {
            $session = Session::update($sessionId, [
                'status' => 'expired',
            ]);

            $this->logger->info('Session de checkout expirée avec succès', [
                'session_id' => $sessionId,
            ]);

            return $session;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de l\'expiration de la session de checkout', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible d\'expirer la session de checkout: ' . $e->getMessage());
        }
    }
}
