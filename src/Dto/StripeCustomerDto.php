<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class StripeCustomerDto
{
    /**
     * @param array<string, string> $address
     * @param array<string, string> $metadata
     */
    public function __construct(
        #[Assert\NotBlank(message: 'L\'email est obligatoire')]
        #[Assert\Email(message: 'L\'email doit être valide')]
        public string $email,
        #[Assert\Length(
            max: 255,
            maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
        )]
        public ?string $name = null,
        #[Assert\Length(
            max: 255,
            maxMessage: 'Le téléphone ne peut pas dépasser {{ limit }} caractères'
        )]
        public ?string $phone = null,
        #[Assert\Type(type: 'array', message: 'L\'adresse doit être un tableau')]
        public array $address = [],
        #[Assert\Type(type: 'array', message: 'Les métadonnées doivent être un tableau')]
        public array $metadata = []
    ) {
    }
}
