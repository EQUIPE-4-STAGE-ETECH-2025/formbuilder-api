<?php

namespace App\Entity;

use App\Repository\SubmissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubmissionRepository::class)]
class Submission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'uuid')]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'submissions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le formulaire est obligatoire')]
    private ?Form $form = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(length: 45)]
    #[Assert\NotBlank(message: 'L\'adresse IP est obligatoire')]
    #[Assert\Length(max: 45, maxMessage: 'L\'adresse IP ne peut pas dépasser {{ limit }} caractères')]
    #[Assert\Ip(message: 'L\'adresse IP n\'est pas valide')]
    private ?string $ipAddress = null;

    public function __construct()
    {
        $this->submittedAt = new \DateTimeImmutable();
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
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }
}
