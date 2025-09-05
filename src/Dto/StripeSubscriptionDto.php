<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class StripeSubscriptionDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'ID du customer Stripe est obligatoire')]
        #[Assert\Regex(
            pattern: '/^cus_[a-zA-Z0-9_]+$/',
            message: 'L\'ID du customer doit avoir le format Stripe valide (cus_...)'
        )]
        public string $customerId,
        #[Assert\NotBlank(message: 'L\'ID du prix Stripe est obligatoire')]
        #[Assert\Regex(
            pattern: '/^price_[a-zA-Z0-9_]+$/',
            message: 'L\'ID du prix doit avoir le format Stripe valide (price_...)'
        )]
        public string $priceId,
        #[Assert\Type(type: 'integer', message: 'La quantité doit être un nombre entier')]
        #[Assert\GreaterThan(value: 0, message: 'La quantité doit être supérieure à 0')]
        public int $quantity = 1,
        #[Assert\Type(type: 'integer', message: 'trial_period_days doit être un nombre entier')]
        #[Assert\GreaterThanOrEqual(value: 0, message: 'trial_period_days doit être positif ou nul')]
        public ?int $trialPeriodDays = null,
        #[Assert\Type(type: 'array', message: 'Les métadonnées doivent être un tableau')]
        public array $metadata = [],
        #[Assert\Choice(
            choices: ['incomplete', 'incomplete_expired', 'trialing', 'active', 'past_due', 'canceled', 'unpaid'],
            message: 'Le statut doit être l\'un des statuts Stripe valides'
        )]
        public ?string $defaultPaymentMethod = null
    ) {
    }
}
