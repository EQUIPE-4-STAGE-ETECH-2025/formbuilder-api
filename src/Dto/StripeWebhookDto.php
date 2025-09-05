<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class StripeWebhookDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'ID de l\'événement est obligatoire')]
        public string $id,
        #[Assert\NotBlank(message: 'Le type d\'événement est obligatoire')]
        #[Assert\Choice(choices: [
            'checkout.session.completed',
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'customer.subscription.trial_will_end',
            'invoice.created',
            'invoice.paid',
            'invoice.payment_failed',
            'invoice.payment_succeeded',
            'payment_method.attached',
            'payment_method.detached',
            'customer.created',
            'customer.updated',
            'customer.deleted',
        ], message: 'Type d\'événement non supporté')]
        public string $type,
        #[Assert\NotNull(message: 'Les données de l\'événement sont obligatoires')]
        #[Assert\Type(type: 'array', message: 'Les données doivent être un tableau')]
        public array $data,
        #[Assert\Type(type: 'integer', message: 'created doit être un timestamp')]
        #[Assert\GreaterThan(value: 0, message: 'created doit être un timestamp valide')]
        public int $created,
        #[Assert\Type(type: 'boolean', message: 'livemode doit être un booléen')]
        public bool $livemode = false,
        #[Assert\Type(type: 'integer', message: 'api_version doit être une chaîne')]
        public ?string $apiVersion = null
    ) {
    }
}
