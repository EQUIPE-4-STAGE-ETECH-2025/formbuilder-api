<?php

namespace App\Dto;

readonly class FormResponseDto
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $status,
        public ?\DateTimeImmutable $publishedAt,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        /** @var array<string, mixed> */
        public array $schema = [],
        public ?int $submissionsCount = null,
        public ?FormVersionDto $currentVersion = null,
        /** @var FormVersionDto[] */
        public array $versions = []
    ) {}
}
