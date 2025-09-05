<?php

namespace App\Controller;

use App\Dto\CheckoutSessionDto;
use App\Dto\StripeSubscriptionDto;
use App\Entity\User;
use App\Service\StripeCheckoutService;
use App\Service\StripeCustomerPortalService;
use App\Service\StripeService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/stripe', name: 'api_stripe_')]
#[IsGranted('ROLE_USER')]
class StripeController extends AbstractController
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly StripeCheckoutService $checkoutService,
        private readonly StripeCustomerPortalService $portalService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Crée une session de checkout pour un abonnement
     */
    #[Route('/checkout-session', name: 'create_checkout_session', methods: ['POST'])]
    public function createCheckoutSession(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            $data = json_decode($request->getContent(), true);

            $checkoutDto = new CheckoutSessionDto(
                priceId: $data['price_id'] ?? '',
                successUrl: $data['success_url'] ?? '',
                cancelUrl: $data['cancel_url'] ?? '',
                quantity: $data['quantity'] ?? 1,
                mode: $data['mode'] ?? 'subscription',
                metadata: $data['metadata'] ?? [],
                allowPromotionCodes: $data['allow_promotion_codes'] ?? true,
                trialPeriodDays: $data['trial_period_days'] ?? null
            );

            $errors = $this->validator->validate($checkoutDto);
            if (count($errors) > 0) {
                return $this->json([
                    'error' => 'Données invalides',
                    'details' => array_map(fn ($error) => $error->getMessage(), iterator_to_array($errors)),
                ], Response::HTTP_BAD_REQUEST);
            }

            $session = $this->checkoutService->createCheckoutSession($user, $checkoutDto);

            $this->logger->info('Session de checkout créée', [
                'user_id' => $user->getId(),
                'session_id' => $session->id,
                'price_id' => $checkoutDto->priceId,
            ]);

            return $this->json([
                'session_id' => $session->id,
                'url' => $session->url,
                'status' => 'success',
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            /** @var User|null $user */
            $user = $this->getUser();
            $this->logger->error('Erreur lors de la création de la session de checkout', [
                'error' => $e->getMessage(),
                'user_id' => $user?->getId(),
            ]);

            return $this->json([
                'error' => 'Impossible de créer la session de checkout',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère le statut d'une session de checkout
     */
    #[Route('/checkout-session/{sessionId}', name: 'get_checkout_session', methods: ['GET'])]
    public function getCheckoutSession(string $sessionId): JsonResponse
    {
        try {
            $session = $this->checkoutService->getCheckoutSession($sessionId);

            return $this->json([
                'session' => [
                    'id' => $session->id,
                    'payment_status' => $session->payment_status,
                    'status' => $session->status,
                    'customer' => $session->customer,
                    'subscription' => $session->subscription,
                    'metadata' => $session->metadata->toArray(),
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération de la session de checkout', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => 'Session introuvable',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Crée une session du portail client
     */
    #[Route('/customer-portal', name: 'create_customer_portal', methods: ['POST'])]
    public function createCustomerPortal(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            $data = json_decode($request->getContent(), true);

            $returnUrl = $data['return_url'] ?? '';
            if (empty($returnUrl)) {
                return $this->json([
                    'error' => 'return_url est obligatoire',
                ], Response::HTTP_BAD_REQUEST);
            }

            $session = $this->portalService->createDefaultPortalSession($user, $returnUrl);

            $this->logger->info('Session du portail client créée', [
                'user_id' => $user->getId(),
                'session_id' => $session->id,
            ]);

            return $this->json([
                'session_id' => $session->id,
                'url' => $session->url,
                'status' => 'success',
            ]);

        } catch (\Exception $e) {
            /** @var User|null $user */
            $user = $this->getUser();
            $this->logger->error('Erreur lors de la création de la session du portail client', [
                'error' => $e->getMessage(),
                'user_id' => $user?->getId(),
            ]);

            return $this->json([
                'error' => 'Impossible de créer la session du portail client',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crée un abonnement directement (sans checkout)
     */
    #[Route('/subscription', name: 'create_subscription', methods: ['POST'])]
    public function createSubscription(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            $data = json_decode($request->getContent(), true);

            // Créer ou récupérer le customer Stripe
            $customer = $this->stripeService->createOrGetCustomer($user);

            $subscriptionDto = new StripeSubscriptionDto(
                customerId: $customer->id,
                priceId: $data['price_id'] ?? '',
                quantity: $data['quantity'] ?? 1,
                trialPeriodDays: $data['trial_period_days'] ?? null,
                metadata: array_merge(['user_id' => $user->getId()], $data['metadata'] ?? []),
                defaultPaymentMethod: $data['default_payment_method'] ?? null
            );

            $errors = $this->validator->validate($subscriptionDto);
            if (count($errors) > 0) {
                return $this->json([
                    'error' => 'Données invalides',
                    'details' => array_map(fn ($error) => $error->getMessage(), iterator_to_array($errors)),
                ], Response::HTTP_BAD_REQUEST);
            }

            $subscription = $this->stripeService->createSubscription($subscriptionDto);

            $this->logger->info('Abonnement créé directement', [
                'user_id' => $user->getId(),
                'subscription_id' => $subscription->id,
                'price_id' => $subscriptionDto->priceId,
            ]);

            return $this->json([
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'trial_end' => $subscription->trial_end,
                ],
                'status' => 'success',
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            /** @var User|null $user */
            $user = $this->getUser();
            $this->logger->error('Erreur lors de la création de l\'abonnement', [
                'error' => $e->getMessage(),
                'user_id' => $user?->getId(),
            ]);

            return $this->json([
                'error' => 'Impossible de créer l\'abonnement',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Met à jour un abonnement (changement de plan)
     */
    #[Route('/subscription/{subscriptionId}', name: 'update_subscription', methods: ['PUT'])]
    public function updateSubscription(string $subscriptionId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $newPriceId = $data['price_id'] ?? '';
            $quantity = $data['quantity'] ?? 1;

            if (empty($newPriceId)) {
                return $this->json([
                    'error' => 'price_id est obligatoire',
                ], Response::HTTP_BAD_REQUEST);
            }

            $subscription = $this->stripeService->updateSubscription($subscriptionId, $newPriceId, $quantity);

            /** @var User|null $user */
            $user = $this->getUser();
            $this->logger->info('Abonnement mis à jour', [
                'subscription_id' => $subscriptionId,
                'new_price_id' => $newPriceId,
                'user_id' => $user?->getId(),
            ]);

            return $this->json([
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                ],
                'status' => 'success',
            ]);

        } catch (\Exception $e) {
            /** @var User|null $user */
            $user = $this->getUser();
            $this->logger->error('Erreur lors de la mise à jour de l\'abonnement', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'user_id' => $user?->getId(),
            ]);

            return $this->json([
                'error' => 'Impossible de mettre à jour l\'abonnement',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Annule un abonnement
     */
    #[Route('/subscription/{subscriptionId}/cancel', name: 'cancel_subscription', methods: ['POST'])]
    public function cancelSubscription(string $subscriptionId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $immediately = $data['immediately'] ?? false;

            $subscription = $this->stripeService->cancelSubscription($subscriptionId, $immediately);

            /** @var User|null $user */
            $user = $this->getUser();
            $this->logger->info('Abonnement annulé', [
                'subscription_id' => $subscriptionId,
                'immediately' => $immediately,
                'user_id' => $user?->getId(),
            ]);

            return $this->json([
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'cancel_at_period_end' => $subscription->cancel_at_period_end,
                    'canceled_at' => $subscription->canceled_at,
                ],
                'status' => 'success',
            ]);

        } catch (\Exception $e) {
            /** @var User|null $user */
            $user = $this->getUser();
            $this->logger->error('Erreur lors de l\'annulation de l\'abonnement', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'user_id' => $user?->getId(),
            ]);

            return $this->json([
                'error' => 'Impossible d\'annuler l\'abonnement',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère les produits et prix disponibles
     */
    #[Route('/products', name: 'get_products', methods: ['GET'])]
    public function getProducts(): JsonResponse
    {
        try {
            $products = $this->stripeService->getActiveProducts();

            $formattedProducts = array_map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'default_price' => $product->default_price ? [
                        'id' => $product->default_price->id,
                        'unit_amount' => $product->default_price->unit_amount,
                        'currency' => $product->default_price->currency,
                        'recurring' => $product->default_price->recurring,
                    ] : null,
                    'metadata' => $product->metadata->toArray(),
                ];
            }, $products);

            return $this->json([
                'products' => $formattedProducts,
                'status' => 'success',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des produits', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => 'Impossible de récupérer les produits',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère les factures d'un utilisateur
     */
    #[Route('/invoices', name: 'get_invoices', methods: ['GET'])]
    public function getInvoices(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            if (! $user->getStripeCustomerId()) {
                return $this->json([
                    'invoices' => [],
                    'status' => 'success',
                ]);
            }

            $limit = min((int) $request->query->get('limit', 10), 100);
            $invoices = $this->stripeService->getCustomerInvoices($user->getStripeCustomerId(), $limit);

            $formattedInvoices = array_map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'amount_paid' => $invoice->amount_paid,
                    'amount_due' => $invoice->amount_due,
                    'currency' => $invoice->currency,
                    'status' => $invoice->status,
                    'created' => $invoice->created,
                    'due_date' => $invoice->due_date,
                    'hosted_invoice_url' => $invoice->hosted_invoice_url,
                    'invoice_pdf' => $invoice->invoice_pdf,
                ];
            }, $invoices);

            return $this->json([
                'invoices' => $formattedInvoices,
                'status' => 'success',
            ]);

        } catch (\Exception $e) {
            /** @var User|null $user */
            $user = $this->getUser();
            $this->logger->error('Erreur lors de la récupération des factures', [
                'error' => $e->getMessage(),
                'user_id' => $user?->getId(),
            ]);

            return $this->json([
                'error' => 'Impossible de récupérer les factures',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
