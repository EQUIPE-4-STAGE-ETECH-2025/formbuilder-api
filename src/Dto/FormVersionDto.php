<?php

namespace App\Dto;

readonly class FormVersionDto
{
    public function __construct(
        public string $id,
        public int $versionNumber,
        /** @var array<string, mixed> */
        public array $schema,
        public \DateTimeImmutable $createdAt,
        /** @var FormFieldDto[] */
        public array $fields = []
    ) {}
}
