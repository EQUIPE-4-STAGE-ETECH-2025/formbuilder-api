<?php

namespace App\Dto;

use App\Entity\User;

class UserListDto
{
    private string $id;
    private string $fullName;
    private string $email;
    private ?string $planName;
    private int $formsCount;
    private int $submissionsCount;

    public function __construct(
        User $user,
        ?string $planName,
        int $formsCount = 0,
        int $submissionsCount = 0
    ) {
        $this->id = $user->getId() ?? '';
        $this->fullName = ($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? '');
        $this->email = $user->getEmail() ?? '';
        $this->planName = $planName;
        $this->formsCount = $formsCount;
        $this->submissionsCount = $submissionsCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->fullName,
            'email' => $this->email,
            'planName' => $this->planName,
            'formsCount' => $this->formsCount,
            'submissionsCount' => $this->submissionsCount,
        ];
    }
}
