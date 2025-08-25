<?php

namespace App\Dto;

class ChangePasswordDto
{
    private ?string $currentPassword = null;
    private ?string $newPassword = null;

    public function getCurrentPassword(): ?string
    {
        return $this->currentPassword;
    }
    public function setCurrentPassword(?string $currentPassword): self
    {
        $this->currentPassword = $currentPassword;

        return $this;
    }

    public function getNewPassword(): ?string
    {
        return $this->newPassword;
    }
    public function setNewPassword(?string $newPassword): self
    {
        $this->newPassword = $newPassword;

        return $this;
    }
}
