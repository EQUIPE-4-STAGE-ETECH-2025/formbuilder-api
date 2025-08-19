<?php

namespace App\Dto;

readonly class FormEmbedDto
{
    public function __construct(
        public string $embedCode,
        public string $formId,
        public string $token,
        public string $embedUrl,
        /** @var array<string, mixed> */
        public array $customization = []
    ) {}
}
