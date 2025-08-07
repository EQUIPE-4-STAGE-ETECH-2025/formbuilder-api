<?php

namespace App\Dto;

class BlackListedTokenDto
{
    public function __construct(
        public readonly string $token,
        public readonly \DateTimeImmutable $expiresAt,
    ) {}
}
