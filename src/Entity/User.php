<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\Cache(usage: 'READ_ONLY')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['user:read'])]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères', maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['user:read', 'user:write'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de famille est obligatoire')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le nom de famille doit contenir au moins {{ limit }} caractères', maxMessage: 'Le nom de famille ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['user:read', 'user:write'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas valide')]
    #[Assert\Length(max: 255, maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['user:read', 'user:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire')]
    #[Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères')]
    #[Groups(['user:write'])]
    private ?string $passwordHash = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?bool $isEmailVerified = false;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le rôle est obligatoire')]
    #[Assert\Choice(choices: ['USER', 'ADMIN'], message: 'Le rôle doit être USER ou ADMIN')]
    #[Groups(['user:read', 'user:write'])]
    private ?string $role = 'USER';

    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, Subscription> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Subscription::class, orphanRemoval: true)]
    #[Groups(['user:read'])]
    private Collection $subscriptions;

    /** @var Collection<int, Form> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Form::class, orphanRemoval: true)]
    #[Groups(['user:read'])]
    private Collection $forms;

    /** @var Collection<int, QuotaStatus> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: QuotaStatus::class, orphanRemoval: true)]
    #[Groups(['user:read'])]
    private Collection $quotaStatuses;

    /** @var Collection<int, AuditLog> */
    #[ORM\OneToMany(mappedBy: 'admin', targetEntity: AuditLog::class)]
    private Collection $auditLogsAsAdmin;

    /** @var Collection<int, AuditLog> */
    #[ORM\OneToMany(mappedBy: 'targetUser', targetEntity: AuditLog::class)]
    private Collection $auditLogsAsTarget;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->subscriptions = new ArrayCollection();
        $this->forms = new ArrayCollection();
        $this->quotaStatuses = new ArrayCollection();
        $this->auditLogsAsAdmin = new ArrayCollection();
        $this->auditLogsAsTarget = new ArrayCollection();
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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function isEmailVerified(): ?bool
    {
        return $this->isEmailVerified;
    }

    public function setIsEmailVerified(bool $isEmailVerified): static
    {
        $this->isEmailVerified = $isEmailVerified;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

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
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): static
    {
        if (! $this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setUser($this);
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->getUser() === $this) {
                $subscription->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Form>
     */
    public function getForms(): Collection
    {
        return $this->forms;
    }

    public function addForm(Form $form): static
    {
        if (! $this->forms->contains($form)) {
            $this->forms->add($form);
            $form->setUser($this);
        }

        return $this;
    }

    public function removeForm(Form $form): static
    {
        if ($this->forms->removeElement($form)) {
            if ($form->getUser() === $this) {
                $form->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, QuotaStatus>
     */
    public function getQuotaStatuses(): Collection
    {
        return $this->quotaStatuses;
    }

    public function addQuotaStatus(QuotaStatus $quotaStatus): static
    {
        if (! $this->quotaStatuses->contains($quotaStatus)) {
            $this->quotaStatuses->add($quotaStatus);
            $quotaStatus->setUser($this);
        }

        return $this;
    }

    public function removeQuotaStatus(QuotaStatus $quotaStatus): static
    {
        if ($this->quotaStatuses->removeElement($quotaStatus)) {
            if ($quotaStatus->getUser() === $this) {
                $quotaStatus->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function getAuditLogsAsAdmin(): Collection
    {
        return $this->auditLogsAsAdmin;
    }

    public function addAuditLogAsAdmin(AuditLog $auditLog): static
    {
        if (! $this->auditLogsAsAdmin->contains($auditLog)) {
            $this->auditLogsAsAdmin->add($auditLog);
            $auditLog->setAdmin($this);
        }

        return $this;
    }

    public function removeAuditLogAsAdmin(AuditLog $auditLog): static
    {
        if ($this->auditLogsAsAdmin->removeElement($auditLog)) {
            if ($auditLog->getAdmin() === $this) {
                $auditLog->setAdmin(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function getAuditLogsAsTarget(): Collection
    {
        return $this->auditLogsAsTarget;
    }

    public function addAuditLogAsTarget(AuditLog $auditLog): static
    {
        if (! $this->auditLogsAsTarget->contains($auditLog)) {
            $this->auditLogsAsTarget->add($auditLog);
            $auditLog->setTargetUser($this);
        }

        return $this;
    }

    public function removeAuditLogAsTarget(AuditLog $auditLog): static
    {
        if ($this->auditLogsAsTarget->removeElement($auditLog)) {
            if ($auditLog->getTargetUser() === $this) {
                $auditLog->setTargetUser(null);
            }
        }

        return $this;
    }

    // UserInterface methods
    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        $roles = [];
        if (null !== $this->role) {
            $roles[] = $this->role;
        }
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getUserIdentifier(): string
    {
        $email = $this->email;
        if (null === $email || '' === $email) {
            throw new \LogicException('User email cannot be null or empty');
        }

        return $email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash ?? '';
    }

    public function setPassword(string $password): static
    {
        $this->passwordHash = $password;

        return $this;
    }
}
