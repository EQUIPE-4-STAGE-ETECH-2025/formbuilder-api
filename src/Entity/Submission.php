<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class Submission
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Form::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Form $form = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $submitter = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $ipAddress = null;

    public function __construct()
    {
        // Génère un UUID automatiquement à la création
        $this->id = Uuid::v4();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getForm(): ?Form
    {
        return $this->form;
    }

    public function setForm(?Form $form): self
    {
        $this->form = $form;

        return $this;
    }

    public function getSubmitter(): ?User
    {
        return $this->submitter;
    }

    public function setSubmitter(?User $user): self
    {
        $this->submitter = $user;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): self
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }
}
