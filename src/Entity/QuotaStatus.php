<?php

namespace App\Entity;

use App\Repository\QuotaStatusRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuotaStatusRepository::class)]
class QuotaStatus
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'quotaStatuses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire')]
    private ?User $user = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'Le mois est obligatoire')]
    private ?\DateTimeInterface $month = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le nombre de formulaires est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le nombre de formulaires doit être positif ou zéro')]
    private ?int $formCount = 0;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le nombre de soumissions est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le nombre de soumissions doit être positif ou zéro')]
    private ?int $submissionCount = 0;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le stockage utilisé est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le stockage utilisé doit être positif ou zéro')]
    private ?int $storageUsedMb = 0;

    #[ORM\Column]
    private ?bool $notified80 = false;

    #[ORM\Column]
    private ?bool $notified100 = false;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getMonth(): ?\DateTimeInterface
    {
        return $this->month;
    }

    public function setMonth(\DateTimeInterface $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getFormCount(): ?int
    {
        return $this->formCount;
    }

    public function setFormCount(int $formCount): static
    {
        $this->formCount = $formCount;

        return $this;
    }

    public function getSubmissionCount(): ?int
    {
        return $this->submissionCount;
    }

    public function setSubmissionCount(int $submissionCount): static
    {
        $this->submissionCount = $submissionCount;

        return $this;
    }

    public function getStorageUsedMb(): ?int
    {
        return $this->storageUsedMb;
    }

    public function setStorageUsedMb(int $storageUsedMb): static
    {
        $this->storageUsedMb = $storageUsedMb;

        return $this;
    }

    public function isNotified80(): ?bool
    {
        return $this->notified80;
    }

    public function setNotified80(bool $notified80): static
    {
        $this->notified80 = $notified80;

        return $this;
    }

    public function isNotified100(): ?bool
    {
        return $this->notified100;
    }

    public function setNotified100(bool $notified100): static
    {
        $this->notified100 = $notified100;

        return $this;
    }
}
