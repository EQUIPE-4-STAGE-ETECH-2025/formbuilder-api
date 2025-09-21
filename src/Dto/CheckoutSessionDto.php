<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CheckoutSessionDto
{
    /**
     * @param array<string, string> $metadata
     */
    public function __construct(
        #[Assert\NotBlank(message: 'L\'ID du prix Stripe est obligatoire')]
        #[Assert\Regex(
            pattern: '/^price_[a-zA-Z0-9_]+$/',
            message: 'L\'ID du prix doit avoir le format Stripe valide (price_...)'
        )]
        public string $priceId,
        #[Assert\NotBlank(message: 'L\'URL de succès est obligatoire')]
        #[Assert\Url(message: 'L\'URL de succès doit être une URL valide', requireTld: true)]
        public string $successUrl,
        #[Assert\NotBlank(message: 'L\'URL d\'annulation est obligatoire')]
        #[Assert\Url(message: 'L\'URL d\'annulation doit être une URL valide', requireTld: true)]
        public string $cancelUrl,
        #[Assert\Type(type: 'integer', message: 'La quantité doit être un nombre entier')]
        #[Assert\GreaterThan(value: 0, message: 'La quantité doit être supérieure à 0')]
        public int $quantity = 1,
        #[Assert\Choice(
            choices: ['subscription', 'payment'],
            message: 'Le mode doit être "subscription" ou "payment"'
        )]
        public string $mode = 'subscription',
        #[Assert\Type(type: 'array', message: 'Les métadonnées doivent être un tableau')]
        public array $metadata = [],
        #[Assert\Type(type: 'boolean', message: 'allow_promotion_codes doit être un booléen')]
        public bool $allowPromotionCodes = true,
    ) {
    }
}
