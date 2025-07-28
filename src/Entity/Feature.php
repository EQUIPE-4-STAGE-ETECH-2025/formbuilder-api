<?php

namespace App\Entity;

use App\Repository\FeatureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FeatureRepository::class)]
class Feature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'uuid')]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le code est obligatoire')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le code doit contenir au moins {{ limit }} caractères', maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères')]
    #[Assert\Regex(pattern: '/^[A-Z_]+$/', message: 'Le code doit contenir uniquement des lettres majuscules et des underscores')]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le libellé doit contenir au moins {{ limit }} caractères', maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères')]
    private ?string $label = null;

    #[ORM\OneToMany(mappedBy: 'feature', targetEntity: PlanFeature::class, orphanRemoval: true)]
    private Collection $planFeatures;

    public function __construct()
    {
        $this->planFeatures = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
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

    /**
     * @return Collection<int, PlanFeature>
     */
    public function getPlanFeatures(): Collection
    {
        return $this->planFeatures;
    }

    public function addPlanFeature(PlanFeature $planFeature): static
    {
        if (!$this->planFeatures->contains($planFeature)) {
            $this->planFeatures->add($planFeature);
            $planFeature->setFeature($this);
        }

        return $this;
    }

    public function removePlanFeature(PlanFeature $planFeature): static
    {
        if ($this->planFeatures->removeElement($planFeature)) {
            if ($planFeature->getFeature() === $this) {
                $planFeature->setFeature(null);
            }
        }

        return $this;
    }
} 