<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class StripeInvoiceDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'ID de la facture Stripe est obligatoire')]
        #[Assert\Regex(
            pattern: '/^in_[a-zA-Z0-9_]+$/',
            message: 'L\'ID de la facture doit avoir le format Stripe valide (in_...)'
        )]
        public string $id,
        #[Assert\NotBlank(message: 'L\'ID du customer est obligatoire')]
        public string $customerId,
        #[Assert\NotBlank(message: 'L\'ID de l\'abonnement est obligatoire')]
        public string $subscriptionId,
        #[Assert\Type(type: 'integer', message: 'Le montant doit être un nombre entier')]
        #[Assert\GreaterThanOrEqual(value: 0, message: 'Le montant doit être positif ou nul')]
        public int $amountPaid,
        #[Assert\Type(type: 'integer', message: 'Le montant dû doit être un nombre entier')]
        #[Assert\GreaterThanOrEqual(value: 0, message: 'Le montant dû doit être positif ou nul')]
        public int $amountDue,
        #[Assert\Choice(
            choices: ['draft', 'open', 'paid', 'uncollectible', 'void'],
            message: 'Le statut doit être l\'un des statuts de facture Stripe valides'
        )]
        public string $status,
        #[Assert\NotBlank(message: 'La devise est obligatoire')]
        #[Assert\Length(exactly: 3, exactMessage: 'La devise doit faire exactement 3 caractères')]
        public string $currency,
        #[Assert\Type(type: 'integer', message: 'created doit être un timestamp')]
        public int $created,
        #[Assert\Type(type: 'integer', message: 'due_date doit être un timestamp')]
        public ?int $dueDate = null,
        #[Assert\Url(message: 'hosted_invoice_url doit être une URL valide', requireTld: true)]
        public ?string $hostedInvoiceUrl = null
    ) {
    }
}
