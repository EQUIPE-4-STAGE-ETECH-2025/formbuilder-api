<?php

namespace App\Service;

use App\Dto\StripeCustomerDto;
use App\Dto\StripeSubscriptionDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\Subscription;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StripeService
{
    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * Crée ou récupère un customer Stripe pour un utilisateur
     */
    public function createOrGetCustomer(User $user, StripeCustomerDto $customerDto = null): Customer
    {
        // Vérifier si l'utilisateur a déjà un customer Stripe
        if ($user->getStripeCustomerId()) {
            try {
                return Customer::retrieve($user->getStripeCustomerId());
            } catch (ApiErrorException $e) {
                $this->logger->warning('Customer Stripe introuvable, création d\'un nouveau', [
                    'user_id' => $user->getId(),
                    'stripe_customer_id' => $user->getStripeCustomerId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Créer un nouveau customer Stripe
        $userEmail = $user->getEmail();
        if (! $userEmail) {
            throw new \RuntimeException('L\'utilisateur doit avoir un email pour créer un customer Stripe');
        }

        $customerData = [
            'email' => $customerDto?->email ?? $userEmail,
            'name' => $customerDto?->name ?? $user->getFirstName() . ' ' . $user->getLastName(),
            'metadata' => array_merge(
                ['user_id' => $user->getId() ?? ''],
                $customerDto?->metadata ?? []
            ),
        ];

        if ($customerDto?->phone) {
            $customerData['phone'] = $customerDto->phone;
        }

        if (! empty($customerDto?->address)) {
            $customerData['address'] = $customerDto->address;
        }

        try {
            $customer = Customer::create($customerData);

            // Sauvegarder l'ID customer dans l'utilisateur
            $user->setStripeCustomerId($customer->id);
            $this->userRepository->save($user, true);

            $this->logger->info('Customer Stripe créé avec succès', [
                'user_id' => $user->getId(),
                'stripe_customer_id' => $customer->id,
            ]);

            return $customer;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la création du customer Stripe', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de créer le customer Stripe: ' . $e->getMessage());
        }
    }

    /**
     * Crée un abonnement Stripe
     */
    public function createSubscription(StripeSubscriptionDto $subscriptionDto): Subscription
    {
        $subscriptionData = [
            'customer' => $subscriptionDto->customerId,
            'items' => [
                [
                    'price' => $subscriptionDto->priceId,
                    'quantity' => $subscriptionDto->quantity,
                ],
            ],
            'metadata' => $subscriptionDto->metadata,
            'expand' => ['latest_invoice.payment_intent'],
        ];


        if ($subscriptionDto->defaultPaymentMethod !== null) {
            $subscriptionData['default_payment_method'] = $subscriptionDto->defaultPaymentMethod;
        }

        try {
            $subscription = Subscription::create($subscriptionData);

            $this->logger->info('Abonnement Stripe créé avec succès', [
                'subscription_id' => $subscription->id,
                'customer_id' => $subscriptionDto->customerId,
                'price_id' => $subscriptionDto->priceId,
            ]);

            return $subscription;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la création de l\'abonnement Stripe', [
                'customer_id' => $subscriptionDto->customerId,
                'price_id' => $subscriptionDto->priceId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de créer l\'abonnement Stripe: ' . $e->getMessage());
        }
    }

    /**
     * Récupère un abonnement Stripe
     */
    public function getSubscription(string $subscriptionId): Subscription
    {
        try {
            return Subscription::retrieve($subscriptionId);
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la récupération de l\'abonnement Stripe', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de récupérer l\'abonnement Stripe: ' . $e->getMessage());
        }
    }

    /**
     * Met à jour un abonnement Stripe (changement de plan)
     */
    public function updateSubscription(string $subscriptionId, string $newPriceId, int $quantity = 1): Subscription
    {
        try {
            $subscription = Subscription::retrieve($subscriptionId);

            $subscription = Subscription::update($subscriptionId, [
                'items' => [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'price' => $newPriceId,
                        'quantity' => $quantity,
                    ],
                ],
                'proration_behavior' => 'create_prorations',
            ]);

            $this->logger->info('Abonnement Stripe mis à jour avec succès', [
                'subscription_id' => $subscriptionId,
                'new_price_id' => $newPriceId,
                'quantity' => $quantity,
            ]);

            return $subscription;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la mise à jour de l\'abonnement Stripe', [
                'subscription_id' => $subscriptionId,
                'new_price_id' => $newPriceId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de mettre à jour l\'abonnement Stripe: ' . $e->getMessage());
        }
    }

    /**
     * Annule un abonnement Stripe
     */
    public function cancelSubscription(string $subscriptionId, bool $immediately = false): Subscription
    {
        try {
            if ($immediately) {
                $subscription = Subscription::update($subscriptionId, [
                    'cancel_at_period_end' => false,
                ]);
                $subscription = $subscription->cancel();
            } else {
                $subscription = Subscription::update($subscriptionId, [
                    'cancel_at_period_end' => true,
                ]);
            }

            $this->logger->info('Abonnement Stripe annulé avec succès', [
                'subscription_id' => $subscriptionId,
                'immediately' => $immediately,
            ]);

            return $subscription;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de l\'annulation de l\'abonnement Stripe', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible d\'annuler l\'abonnement Stripe: ' . $e->getMessage());
        }
    }

    /**
     * Récupère tous les prix d'un produit
     * @return array<mixed>
     */
    public function getProductPrices(string $productId): array
    {
        try {
            $prices = Price::all([
                'product' => $productId,
                'active' => true,
            ]);

            return $prices->data;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la récupération des prix du produit', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de récupérer les prix du produit: ' . $e->getMessage());
        }
    }

    /**
     * Récupère tous les produits actifs
     * @return array<mixed>
     */
    public function getActiveProducts(): array
    {
        try {
            $products = Product::all([
                'active' => true,
                'expand' => ['data.default_price'],
            ]);

            return $products->data;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la récupération des produits actifs', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de récupérer les produits actifs: ' . $e->getMessage());
        }
    }

    /**
     * Récupère les factures d'un customer
     * @return array<mixed>
     */
    public function getCustomerInvoices(string $customerId, int $limit = 10): array
    {
        try {
            $invoices = Invoice::all([
                'customer' => $customerId,
                'limit' => $limit,
            ]);

            return $invoices->data;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la récupération des factures du customer', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de récupérer les factures du customer: ' . $e->getMessage());
        }
    }

    /**
     * Relance le paiement d'une facture
     */
    public function retryInvoicePayment(string $invoiceId): Invoice
    {
        try {
            $invoice = Invoice::retrieve($invoiceId);
            $invoice = $invoice->pay();

            $this->logger->info('Paiement de facture relancé avec succès', [
                'invoice_id' => $invoiceId,
            ]);

            return $invoice;
        } catch (ApiErrorException $e) {
            $this->logger->error('Erreur lors de la relance du paiement de la facture', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Impossible de relancer le paiement de la facture: ' . $e->getMessage());
        }
    }
}
