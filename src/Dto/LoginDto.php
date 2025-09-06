<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class LoginDto
{
    #[Assert\NotBlank(message: 'L\'email est requis')]
    #[Assert\Email(message: 'Format d\'email invalide')]
    private string $email;

    #[Assert\NotBlank(message: 'Le mot de passe est requis')]
    private string $password;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
    
    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }
}