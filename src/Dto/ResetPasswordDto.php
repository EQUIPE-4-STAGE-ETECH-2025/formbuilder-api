<?php

namespace App\Dto;

use App\Validator\PasswordStrength;
use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordDto
{
    #[Assert\NotBlank(message: 'Le token est requis.')]
    private string $token;

    #[Assert\NotBlank(message: 'Le nouveau mot de passe est requis.')]
    #[PasswordStrength]
    private string $newPassword;

    public function getNewPassword(): ?string
    {
        return $this->newPassword;
    }
    public function setNewPassword(?string $newPassword): self
    {
        $this->newPassword = $newPassword;
        return $this;
    }
    public function getToken(): ?string
    {
        return $this->token;
    }
    public function setToken(?string $token): self
    {
        $this->token = $token;
        return $this;
    }
}
