<?php

namespace App\Entity;

use App\Repository\FormVersionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FormVersionRepository::class)]
class FormVersion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'formVersions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le formulaire est obligatoire')]
    private ?Form $form = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le numéro de version est obligatoire')]
    #[Assert\Positive(message: 'Le numéro de version doit être positif')]
    private ?int $versionNumber = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $schema = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, FormField>
     */
    #[ORM\OneToMany(mappedBy: 'formVersion', targetEntity: FormField::class, orphanRemoval: true)]
    private Collection $formFields;

    public function __construct()
    {
        $this->formFields = new ArrayCollection();
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

    public function getVersionNumber(): ?int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): static
    {
        $this->versionNumber = $versionNumber;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * @param array<string, mixed> $schema
     */
    public function setSchema(array $schema): static
    {
        $this->schema = $schema;

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

    /**
     * @return Collection<int, FormField>
     */
    public function getFormFields(): Collection
    {
        return $this->formFields;
    }

    public function addFormField(FormField $formField): static
    {
        if (!$this->formFields->contains($formField)) {
            $this->formFields->add($formField);
            $formField->setFormVersion($this);
        }

        return $this;
    }

    public function removeFormField(FormField $formField): static
    {
        if ($this->formFields->removeElement($formField)) {
            if ($formField->getFormVersion() === $this) {
                $formField->setFormVersion(null);
            }
        }

        return $this;
    }
}
