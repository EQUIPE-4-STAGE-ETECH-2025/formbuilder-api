<?php

namespace App\Dto;

use App\Entity\User;

class UserResponseDto
{
    public function __construct(private readonly User $user)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $createdAt = $this->user->getCreatedAt();

        return [
            'id' => $this->user->getId(),
            'firstName' => $this->user->getFirstName(),
            'lastName' => $this->user->getLastName(),
            'email' => $this->user->getEmail(),
            'role' => $this->user->getRole(),
            'isEmailVerified' => $this->user->isEmailVerified(),
            'createdAt' => $createdAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function getEmail(): ?string
    {
        return $this->user->getEmail();
    }
}
