<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class UpdateFormDto
{
    public function __construct(
        #[Assert\Length(min: 3, max: 255, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères', maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
        public ?string $title = null,

        #[Assert\Length(min: 10, max: 1000, minMessage: 'La description doit contenir au moins {{ limit }} caractères', maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
        public ?string $description = null,

        #[Assert\Choice(choices: ['DRAFT', 'PUBLISHED', 'ARCHIVED'], message: 'Le statut doit être DRAFT, PUBLISHED ou ARCHIVED')]
        public ?string $status = null,

        /** @var array<string, mixed>|null */
        #[Assert\Type(type: 'array', message: 'Le schéma doit être un tableau')]
        public ?array $schema = null
    ) {}
}
