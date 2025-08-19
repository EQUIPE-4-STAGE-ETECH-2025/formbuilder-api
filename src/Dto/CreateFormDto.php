<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateFormDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le titre est obligatoire')]
        #[Assert\Length(min: 3, max: 255, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères', maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
        public string $title,
        #[Assert\NotBlank(message: 'La description est obligatoire')]
        #[Assert\Length(min: 10, max: 1000, minMessage: 'La description doit contenir au moins {{ limit }} caractères', maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
        public string $description,
        #[Assert\Choice(choices: ['DRAFT', 'PUBLISHED', 'ARCHIVED'], message: 'Le statut doit être DRAFT, PUBLISHED ou ARCHIVED')]
        public string $status = 'DRAFT',

        /** @var array<string, mixed> */
        #[Assert\Type(type: 'array', message: 'Le schéma doit être un tableau')]
        public array $schema = []
    ) {
    }
}
