<?php

declare(strict_types=1);

namespace PayKit\Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use PayKit\Bundle\Catalog\ResetInterval;

/**
 * A customer's grant for one feature, derived from one subscription. A null limit means unlimited.
 */
#[ORM\Entity]
#[ORM\Table(name: 'paykit_entitlement')]
#[ORM\Index(name: 'paykit_entitlement_customer_feature_idx', columns: ['customer_id', 'feature_id'])]
#[ORM\Index(name: 'paykit_entitlement_subscription_idx', columns: ['subscription_id'])]
class Entitlement
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 40)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Subscription::class)]
    #[ORM\JoinColumn(name: 'subscription_id', referencedColumnName: 'id', nullable: false)]
    private Subscription $subscription;

    #[ORM\Column(name: 'customer_id', length: 191)]
    private string $customerId;

    #[ORM\Column(name: 'feature_id', length: 64)]
    private string $featureId;

    #[ORM\Column(name: 'entitlement_limit', nullable: true)]
    private ?int $limit;

    #[ORM\Column(name: 'balance', nullable: true)]
    private ?int $balance;

    #[ORM\Column(name: 'reset_interval', length: 16, nullable: true)]
    private ?string $resetInterval;

    #[ORM\Column(name: 'next_reset_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextResetAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Subscription $subscription, string $featureId, ?int $limit, ?ResetInterval $reset, \DateTimeImmutable $now)
    {
        $this->id = Id::generate('ent');
        $this->subscription = $subscription;
        $this->customerId = $subscription->getCustomer()->getId();
        $this->featureId = $featureId;
        $this->limit = $limit;
        $this->balance = $limit;
        $this->resetInterval = $reset?->value;
        $this->nextResetAt = (null !== $limit && null !== $reset) ? $reset->addTo($now) : null;
        $this->updatedAt = $now;
    }

    public function getId(): string { return $this->id; }
    public function getSubscription(): Subscription { return $this->subscription; }
    public function getCustomerId(): string { return $this->customerId; }
    public function getFeatureId(): string { return $this->featureId; }
    public function getLimit(): ?int { return $this->limit; }
    public function getBalance(): ?int { return $this->balance; }
    public function getResetInterval(): ?ResetInterval { return null !== $this->resetInterval ? ResetInterval::from($this->resetInterval) : null; }
    public function getNextResetAt(): ?\DateTimeImmutable { return $this->nextResetAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function isUnlimited(): bool { return null === $this->limit; }

    /** Lazy reset: returns true when the balance was refilled. */
    public function resetIfDue(\DateTimeImmutable $now): bool
    {
        $interval = $this->getResetInterval();
        if (null === $this->limit || null === $interval || null === $this->nextResetAt || $this->nextResetAt > $now) {
            return false;
        }
        $this->balance = $this->limit;
        $this->nextResetAt = $interval->nextAfter($this->nextResetAt, $now);
        $this->updatedAt = $now;

        return true;
    }
}
