<?php

namespace App\Entity;

use App\Repository\FormFieldRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FormFieldRepository::class)]
class FormField
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'formFields')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La version du formulaire est obligatoire')]
    private ?FormVersion $formVersion = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le libellé doit contenir au moins {{ limit }} caractères', maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères')]
    private ?string $label = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le type est obligatoire')]
    #[Assert\Choice(choices: ['text', 'email', 'number', 'textarea', 'select', 'checkbox', 'radio', 'date', 'file'], message: 'Le type doit être une valeur valide')]
    private ?string $type = null;

    #[ORM\Column]
    private ?bool $isRequired = false;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $options = [];

    #[ORM\Column]
    #[Assert\NotNull(message: 'La position est obligatoire')]
    #[Assert\Positive(message: 'La position doit être positive')]
    private ?int $position = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $validationRules = [];

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getFormVersion(): ?FormVersion
    {
        return $this->formVersion;
    }

    public function setFormVersion(?FormVersion $formVersion): static
    {
        $this->formVersion = $formVersion;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

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

    public function isRequired(): ?bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    /**
     * @param array<string, mixed> $validationRules
     */
    public function setValidationRules(array $validationRules): static
    {
        $this->validationRules = $validationRules;

        return $this;
    }
}
