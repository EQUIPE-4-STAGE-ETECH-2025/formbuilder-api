<?php

namespace App\Dto;

use App\Entity\User;

class UserResponseDto
{
    public function __construct(private readonly User $user)
    {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->user->getId(),
            'firstName' => $this->user->getFirstName(),
            'lastName' => $this->user->getLastName(),
            'email' => $this->user->getEmail(),
            'role' => $this->user->getRole(),
            'isEmailVerified' => $this->user->isEmailVerified(),
            'createdAt' => $this->user->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    public function getEmail(): string
    {
        return $this->user->getEmail();
    }
}
