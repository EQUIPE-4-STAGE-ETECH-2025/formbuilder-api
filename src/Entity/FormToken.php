<?php

namespace App\Entity;

use App\Repository\FormTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FormTokenRepository::class)]
class FormToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'formTokens')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le formulaire est obligatoire')]
    private ?Form $form = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le token est obligatoire')]
    private ?string $token = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type de token est obligatoire')]
    private ?string $type = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date d\'expiration est obligatoire')]
    #[Assert\GreaterThan('today', message: 'La date d\'expiration doit être dans le futur')]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getForm(): ?Form
    {
        return $this->form;
    }

    public function setForm(?Form $form): static
    {
        $this->form = $form;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
