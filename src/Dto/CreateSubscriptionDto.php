<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateSubscriptionDto
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $planId;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $userEmail;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->planId = $data['planId'] ?? '';
        $this->userEmail = $data['userEmail'] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'planId' => $this->planId,
            'userEmail' => $this->userEmail,
        ];
    }
}
