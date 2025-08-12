<?php

namespace App\Dto;

use App\Validator\PasswordStrength;
use Symfony\Component\Validator\Constraints as Assert;

class RegisterDto
{
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $firstName = null;

    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $lastName = null;

    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'Email invalide')]
    #[Assert\Length(max: 255)]
    private ?string $email = null;

    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire')]
    #[PasswordStrength]
    private ?string $password = null;

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }
    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }
    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }
}

