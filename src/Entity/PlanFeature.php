<?php

namespace App\Entity;

use App\Repository\PlanFeatureRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlanFeatureRepository::class)]
class PlanFeature
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'planFeatures')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le plan est obligatoire')]
    private ?Plan $plan = null;

    #[ORM\ManyToOne(inversedBy: 'planFeatures')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La fonctionnalité est obligatoire')]
    private ?Feature $feature = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function getFeature(): ?Feature
    {
        return $this->feature;
    }

    public function setFeature(?Feature $feature): static
    {
        $this->feature = $feature;

        return $this;
    }
}
