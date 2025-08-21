<?php

namespace App\Dto;

readonly class FormFieldDto
{
    public function __construct(
        public string $id,
        public string $label,
        public string $type,
        public bool $isRequired,
        /** @var array<string, mixed> */
        public array $options,
        public int $position,
        /** @var array<string, mixed> */
        public array $validationRules
    ) {
    }
}
