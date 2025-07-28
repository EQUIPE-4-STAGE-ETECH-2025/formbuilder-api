<?php

namespace App\Entity;

use App\Repository\FormTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FormTokenRepository::class)]
class FormToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'uuid')]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'formTokens')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le formulaire est obligatoire')]
    private ?Form $form = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le JWT est obligatoire')]
    private ?string $jwt = null;

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

    public function getForm(): ?Form
    {
        return $this->form;
    }

    public function setForm(?Form $form): static
    {
        $this->form = $form;

        return $this;
    }

    public function getJwt(): ?string
    {
        return $this->jwt;
    }

    public function setJwt(string $jwt): static
    {
        $this->jwt = $jwt;

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
