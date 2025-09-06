<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CustomerPortalDto
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        #[Assert\NotBlank(message: 'L\'ID du customer Stripe est obligatoire')]
        #[Assert\Regex(
            pattern: '/^cus_[a-zA-Z0-9_]+$/',
            message: 'L\'ID du customer doit avoir le format Stripe valide (cus_...)'
        )]
        public string $customerId,
        #[Assert\NotBlank(message: 'L\'URL de retour est obligatoire')]
        #[Assert\Url(message: 'L\'URL de retour doit être une URL valide')]
        public string $returnUrl,
        #[Assert\Type(type: 'array', message: 'La configuration doit être un tableau')]
        public array $configuration = []
    ) {
    }
}
