<?php

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
class Plan
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères', maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    private ?string $name = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le prix est obligatoire')]
    #[Assert\Positive(message: 'Le prix doit être positif')]
    #[Assert\Range(min: 0, max: 999999, minMessage: 'Le prix doit être au moins {{ limit }}', maxMessage: 'Le prix ne peut pas dépasser {{ limit }}')]
    private ?int $priceCents = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'ID produit Stripe est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'L\'ID produit Stripe ne peut pas dépasser {{ limit }} caractères')]
    private ?string $stripeProductId = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le nombre maximum de formulaires est obligatoire')]
    #[Assert\Positive(message: 'Le nombre maximum de formulaires doit être positif')]
    #[Assert\Range(min: 1, max: 1000, minMessage: 'Le nombre maximum de formulaires doit être au moins {{ limit }}', maxMessage: 'Le nombre maximum de formulaires ne peut pas dépasser {{ limit }}')]
    private ?int $maxForms = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le nombre maximum de soumissions par mois est obligatoire')]
    #[Assert\Positive(message: 'Le nombre maximum de soumissions par mois doit être positif')]
    #[Assert\Range(min: 1, max: 100000, minMessage: 'Le nombre maximum de soumissions par mois doit être au moins {{ limit }}', maxMessage: 'Le nombre maximum de soumissions par mois ne peut pas dépasser {{ limit }}')]
    private ?int $maxSubmissionsPerMonth = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le stockage maximum en MB est obligatoire')]
    #[Assert\Positive(message: 'Le stockage maximum en MB doit être positif')]
    #[Assert\Range(min: 1, max: 10000, minMessage: 'Le stockage maximum en MB doit être au moins {{ limit }}', maxMessage: 'Le stockage maximum en MB ne peut pas dépasser {{ limit }}')]
    private ?int $maxStorageMb = null;

    /** @var Collection<int, Subscription> */
    #[ORM\OneToMany(mappedBy: 'plan', targetEntity: Subscription::class, orphanRemoval: true)]
    private Collection $subscriptions;

    /** @var Collection<int, PlanFeature> */
    #[ORM\OneToMany(mappedBy: 'plan', targetEntity: PlanFeature::class, orphanRemoval: true)]
    private Collection $planFeatures;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->planFeatures = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPriceCents(): ?int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): static
    {
        $this->priceCents = $priceCents;

        return $this;
    }

    public function getStripeProductId(): ?string
    {
        return $this->stripeProductId;
    }

    public function setStripeProductId(string $stripeProductId): static
    {
        $this->stripeProductId = $stripeProductId;

        return $this;
    }

    public function getMaxForms(): ?int
    {
        return $this->maxForms;
    }

    public function setMaxForms(int $maxForms): static
    {
        $this->maxForms = $maxForms;

        return $this;
    }

    public function getMaxSubmissionsPerMonth(): ?int
    {
        return $this->maxSubmissionsPerMonth;
    }

    public function setMaxSubmissionsPerMonth(int $maxSubmissionsPerMonth): static
    {
        $this->maxSubmissionsPerMonth = $maxSubmissionsPerMonth;

        return $this;
    }

    public function getMaxStorageMb(): ?int
    {
        return $this->maxStorageMb;
    }

    public function setMaxStorageMb(int $maxStorageMb): static
    {
        $this->maxStorageMb = $maxStorageMb;

        return $this;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): static
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setPlan($this);
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->getPlan() === $this) {
                $subscription->setPlan(null);
            }
        }

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
            $planFeature->setPlan($this);
        }

        return $this;
    }

    public function removePlanFeature(PlanFeature $planFeature): static
    {
        if ($this->planFeatures->removeElement($planFeature)) {
            if ($planFeature->getPlan() === $this) {
                $planFeature->setPlan(null);
            }
        }

        return $this;
    }
}
