<?php

declare(strict_types=1);

namespace PayKit\Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pending subscribe intent created when the customer is sent to a hosted checkout.
 * Completed when the provider reports `checkout.completed`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'paykit_checkout_session')]
#[ORM\UniqueConstraint(name: 'paykit_checkout_provider_unique', columns: ['provider_session_id'])]
class CheckoutSession
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 40)]
    private string $id;

    #[ORM\Column(name: 'provider_session_id', length: 255)]
    private string $providerSessionId;

    #[ORM\Column(name: 'customer_id', length: 191)]
    private string $customerId;

    #[ORM\Column(name: 'product_internal_id', length: 40)]
    private string $productInternalId;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $providerSessionId, string $customerId, string $productInternalId, \DateTimeImmutable $now)
    {
        $this->id = Id::generate('cs');
        $this->providerSessionId = $providerSessionId;
        $this->customerId = $customerId;
        $this->productInternalId = $productInternalId;
        $this->createdAt = $now;
    }

    public function getId(): string { return $this->id; }
    public function getProviderSessionId(): string { return $this->providerSessionId; }
    public function getCustomerId(): string { return $this->customerId; }
    public function getProductInternalId(): string { return $this->productInternalId; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function isCompleted(): bool { return null !== $this->completedAt; }
    public function complete(\DateTimeImmutable $now): void { $this->completedAt = $now; }
}
