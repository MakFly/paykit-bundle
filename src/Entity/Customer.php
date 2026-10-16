<?php

declare(strict_types=1);

namespace PayKit\Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Your app's billing identity (a user id, an org id...). The id is yours; provider ids stay internal.
 */
#[ORM\Entity]
#[ORM\Table(name: 'paykit_customer')]
#[ORM\Index(name: 'paykit_customer_provider_idx', columns: ['provider_customer_id'])]
class Customer
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 191)]
    private string $id;

    #[ORM\Column(name: 'email', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'name', length: 255, nullable: true)]
    private ?string $name = null;

    /** @var array<string, string>|null */
    #[ORM\Column(name: 'metadata', type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'provider_customer_id', length: 255, nullable: true)]
    private ?string $providerCustomerId = null;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, string>|null $metadata
     */
    public function __construct(string $id, \DateTimeImmutable $now, ?string $email = null, ?string $name = null, ?array $metadata = null)
    {
        $this->id = $id;
        $this->email = $email;
        $this->name = $name;
        $this->metadata = $metadata;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): string { return $this->id; }
    public function getEmail(): ?string { return $this->email; }
    public function getName(): ?string { return $this->name; }
    /** @return array<string, string>|null */
    public function getMetadata(): ?array { return $this->metadata; }
    public function getProviderCustomerId(): ?string { return $this->providerCustomerId; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /**
     * @param array<string, string>|null $metadata
     */
    public function update(?string $email, ?string $name, ?array $metadata, \DateTimeImmutable $now): void
    {
        $this->email = $email ?? $this->email;
        $this->name = $name ?? $this->name;
        $this->metadata = $metadata ?? $this->metadata;
        $this->deletedAt = null;
        $this->updatedAt = $now;
    }

    public function linkProviderCustomer(string $providerCustomerId, \DateTimeImmutable $now): void
    {
        $this->providerCustomerId = $providerCustomerId;
        $this->updatedAt = $now;
    }

    public function markDeleted(\DateTimeImmutable $now): void
    {
        $this->deletedAt = $now;
        $this->updatedAt = $now;
    }

    public function isDeleted(): bool { return null !== $this->deletedAt; }
}
