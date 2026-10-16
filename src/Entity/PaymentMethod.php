<?php

declare(strict_types=1);

namespace PayKit\Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'paykit_payment_method')]
#[ORM\UniqueConstraint(name: 'paykit_payment_method_provider_unique', columns: ['provider_method_id'])]
class PaymentMethod
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 40)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: false)]
    private Customer $customer;

    #[ORM\Column(name: 'provider_method_id', length: 255)]
    private string $providerMethodId;

    #[ORM\Column(name: 'type', length: 32)]
    private string $type;

    #[ORM\Column(name: 'brand', length: 32, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(name: 'last4', length: 4, nullable: true)]
    private ?string $last4 = null;

    #[ORM\Column(name: 'expiry_month', nullable: true)]
    private ?int $expiryMonth = null;

    #[ORM\Column(name: 'expiry_year', nullable: true)]
    private ?int $expiryYear = null;

    #[ORM\Column(name: 'is_default')]
    private bool $isDefault = false;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(Customer $customer, string $providerMethodId, string $type)
    {
        $this->id = Id::generate('pm');
        $this->customer = $customer;
        $this->providerMethodId = $providerMethodId;
        $this->type = $type;
    }

    public function update(string $type, ?string $brand, ?string $last4, ?int $expiryMonth, ?int $expiryYear, bool $isDefault): void
    {
        $this->type = $type;
        $this->brand = $brand;
        $this->last4 = $last4;
        $this->expiryMonth = $expiryMonth;
        $this->expiryYear = $expiryYear;
        $this->isDefault = $isDefault;
        $this->deletedAt = null;
    }

    public function markDeleted(\DateTimeImmutable $now): void
    {
        $this->deletedAt = $now;
        $this->isDefault = false;
    }

    public function getId(): string { return $this->id; }
    public function getCustomer(): Customer { return $this->customer; }
    public function getProviderMethodId(): string { return $this->providerMethodId; }
    public function getType(): string { return $this->type; }
    public function getBrand(): ?string { return $this->brand; }
    public function getLast4(): ?string { return $this->last4; }
    public function getExpiryMonth(): ?int { return $this->expiryMonth; }
    public function getExpiryYear(): ?int { return $this->expiryYear; }
    public function isDefault(): bool { return $this->isDefault; }
    public function isDeleted(): bool { return null !== $this->deletedAt; }
}
