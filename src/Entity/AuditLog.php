<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'uuid')]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'auditLogsAsAdmin')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'administrateur est obligatoire')]
    private ?User $admin = null;

    #[ORM\ManyToOne(inversedBy: 'auditLogsAsTarget')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'utilisateur cible est obligatoire')]
    private ?User $targetUser = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'action est obligatoire')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'L\'action doit contenir au moins {{ limit }} caractères', maxMessage: 'L\'action ne peut pas dépasser {{ limit }} caractères')]
    private ?string $action = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La raison est obligatoire')]
    #[Assert\Length(min: 10, max: 1000, minMessage: 'La raison doit contenir au moins {{ limit }} caractères', maxMessage: 'La raison ne peut pas dépasser {{ limit }} caractères')]
    private ?string $reason = null;

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

    public function getAdmin(): ?User
    {
        return $this->admin;
    }

    public function setAdmin(?User $admin): static
    {
        $this->admin = $admin;

        return $this;
    }

    public function getTargetUser(): ?User
    {
        return $this->targetUser;
    }

    public function setTargetUser(?User $targetUser): static
    {
        $this->targetUser = $targetUser;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

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
