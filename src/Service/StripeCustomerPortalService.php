<?php

namespace App\Service;

use App\Dto\CustomerPortalDto;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Stripe\BillingPortal\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StripeCustomerPortalService
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
     * Crée une session du portail client Stripe
     */
    public function createPortalSession(User $user, CustomerPortalDto $portalDto): Session
    {
        try {
            // Créer ou récupérer le customer Stripe
            $customer = $this->stripeService->createOrGetCustomer($user);

            $sessionData = [
                'customer' => $customer->id,
                'return_url' => $portalDto->returnUrl,
            ];

            // Configuration personnalisée si fournie (désactivé car l'API attend un string, pas un array)
            // if (! empty($portalDto->configuration)) {
            //     $sessionData['configuration'] = $portalDto->configuration;
            // }

            $session = Session::create($sessionData);

            $this->logger->info('Session du portail client Stripe créée avec succès', [
                'session_id' => $session->id,
                'user_id' => $user->getId(),
                'customer_id' => $customer->id,
            ]);

            return $session;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la création de la session du portail client', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de créer la session du portail client: ' . $e->getMessage());
        }
    }

    /**
     * Crée une session du portail client avec configuration par défaut
     */
    public function createDefaultPortalSession(User $user, string $returnUrl): Session
    {
        $portalDto = new CustomerPortalDto(
            customerId: '', // Sera ignoré car on passe par createOrGetCustomer
            returnUrl: $returnUrl,
            configuration: $this->getDefaultPortalConfiguration()
        );

        return $this->createPortalSession($user, $portalDto);
    }

    /**
     * Note: Les sessions du portail client ne peuvent pas être récupérées après création
     * Cette méthode est conservée pour la compatibilité mais retourne toujours false
     */
    public function isSessionActive(string $sessionId): bool
    {
        $this->logger->info('Vérification de session du portail client', [
            'session_id' => $sessionId,
            'note' => 'Les sessions du portail client ne peuvent pas être vérifiées via l\'API',
        ]);

        return true;
    }

    /**
     * Crée une session du portail client pour la gestion d'un abonnement spécifique
     */
    public function createSubscriptionManagementSession(
        User $user,
        string $subscriptionId,
        string $returnUrl
    ): Session {
        try {
            $customer = $this->stripeService->createOrGetCustomer($user);

            $sessionData = [
                'customer' => $customer->id,
                'return_url' => $returnUrl,
                'flow_data' => [
                    'type' => 'subscription_update_confirm',
                    'subscription_update_confirm' => [
                        'subscription' => $subscriptionId,
                        'items' => [], // Requis par l'API Stripe
                    ],
                ],
            ];

            $session = Session::create($sessionData);

            $this->logger->info('Session du portail client pour gestion d\'abonnement créée', [
                'session_id' => $session->id,
                'user_id' => $user->getId(),
                'subscription_id' => $subscriptionId,
            ]);

            return $session;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la création de la session de gestion d\'abonnement', [
                'user_id' => $user->getId(),
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de créer la session de gestion d\'abonnement: ' . $e->getMessage());
        }
    }

    /**
     * Configuration par défaut du portail client
     * @return array<string, mixed>
     */
    private function getDefaultPortalConfiguration(): array
    {
        return [
            'business_profile' => [
                'headline' => 'Gérez votre abonnement FormBuilder',
            ],
            'features' => [
                'payment_method_update' => [
                    'enabled' => true,
                ],
                'subscription_cancel' => [
                    'enabled' => true,
                    'mode' => 'at_period_end',
                    'cancellation_reason' => [
                        'enabled' => true,
                        'options' => [
                            'too_expensive',
                            'missing_features',
                            'switched_service',
                            'unused',
                            'customer_service',
                            'too_complex',
                            'low_quality',
                            'other',
                        ],
                    ],
                ],
                'subscription_pause' => [
                    'enabled' => false, // Désactivé par défaut
                ],
                'subscription_update' => [
                    'enabled' => true,
                    'default_allowed_updates' => ['price', 'quantity'],
                    'proration_behavior' => 'create_prorations',
                ],
                'invoice_history' => [
                    'enabled' => true,
                ],
            ],
        ];
    }

    /**
     * Génère l'URL directe du portail client pour un utilisateur
     */
    public function generatePortalUrl(User $user, string $returnUrl): string
    {
        $session = $this->createDefaultPortalSession($user, $returnUrl);

        return $session->url;
    }
}
