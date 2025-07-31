<?php

namespace App\Entity;

use App\Repository\FormRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FormRepository::class)]
class Form
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'forms')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères', maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(min: 10, max: 1000, minMessage: 'La description doit contenir au moins {{ limit }} caractères', maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    #[Assert\Choice(choices: ['DRAFT', 'PUBLISHED', 'ARCHIVED'], message: 'Le statut doit être DRAFT, PUBLISHED ou ARCHIVED')]
    private ?string $status = 'DRAFT';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, FormVersion> */
    #[ORM\OneToMany(mappedBy: 'form', targetEntity: FormVersion::class, orphanRemoval: true)]
    private Collection $formVersions;

    /** @var Collection<int, Submission> */
    #[ORM\OneToMany(mappedBy: 'form', targetEntity: Submission::class, orphanRemoval: true)]
    private Collection $submissions;

    /** @var Collection<int, FormToken> */
    #[ORM\OneToMany(mappedBy: 'form', targetEntity: FormToken::class, orphanRemoval: true)]
    private Collection $formTokens;

    public function __construct()
    {
        $this->formVersions = new ArrayCollection();
        $this->submissions = new ArrayCollection();
        $this->formTokens = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, FormVersion>
     */
    public function getFormVersions(): Collection
    {
        return $this->formVersions;
    }

    public function addFormVersion(FormVersion $formVersion): static
    {
        if (!$this->formVersions->contains($formVersion)) {
            $this->formVersions->add($formVersion);
            $formVersion->setForm($this);
        }

        return $this;
    }

    public function removeFormVersion(FormVersion $formVersion): static
    {
        if ($this->formVersions->removeElement($formVersion)) {
            if ($formVersion->getForm() === $this) {
                $formVersion->setForm(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Submission>
     */
    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function addSubmission(Submission $submission): static
    {
        if (!$this->submissions->contains($submission)) {
            $this->submissions->add($submission);
            $submission->setForm($this);
        }

        return $this;
    }

    public function removeSubmission(Submission $submission): static
    {
        if ($this->submissions->removeElement($submission)) {
            if ($submission->getForm() === $this) {
                $submission->setForm(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FormToken>
     */
    public function getFormTokens(): Collection
    {
        return $this->formTokens;
    }

    public function addFormToken(FormToken $formToken): static
    {
        if (!$this->formTokens->contains($formToken)) {
            $this->formTokens->add($formToken);
            $formToken->setForm($this);
        }

        return $this;
    }

    public function removeFormToken(FormToken $formToken): static
    {
        if ($this->formTokens->removeElement($formToken)) {
            if ($formToken->getForm() === $this) {
                $formToken->setForm(null);
            }
        }

        return $this;
    }
}
