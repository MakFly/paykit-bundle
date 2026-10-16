<?php

declare(strict_types=1);

namespace PayKit\Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'paykit_subscription')]
#[ORM\Index(name: 'paykit_subscription_customer_status_idx', columns: ['customer_id', 'status', 'ended_at'])]
#[ORM\Index(name: 'paykit_subscription_provider_idx', columns: ['provider_subscription_id'])]
class Subscription
{
    /** Statuses granting entitlements. */
    public const ACTIVE_STATUSES = ['active', 'trialing'];

    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 40)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: false)]
    private Customer $customer;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_internal_id', referencedColumnName: 'internal_id', nullable: false)]
    private Product $product;

    #[ORM\Column(name: 'provider_subscription_id', length: 255, nullable: true)]
    private ?string $providerSubscriptionId = null;

    #[ORM\Column(name: 'provider_schedule_id', length: 255, nullable: true)]
    private ?string $providerScheduleId = null;

    #[ORM\Column(name: 'status', length: 32)]
    private string $status;

    #[ORM\Column(name: 'cancel_at_period_end')]
    private bool $cancelAtPeriodEnd = false;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'trial_ends_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $trialEndsAt = null;

    #[ORM\Column(name: 'current_period_start_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $currentPeriodStartAt = null;

    #[ORM\Column(name: 'current_period_end_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $currentPeriodEndAt = null;

    #[ORM\Column(name: 'canceled_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $canceledAt = null;

    #[ORM\Column(name: 'ended_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    /** Plan id the customer moves to at period end (scheduled downgrade / cancel-to-free). */
    #[ORM\Column(name: 'scheduled_plan_id', length: 64, nullable: true)]
    private ?string $scheduledPlanId = null;

    #[ORM\Column(name: 'quantity')]
    private int $quantity = 1;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Customer $customer, Product $product, string $status, \DateTimeImmutable $now)
    {
        $this->id = Id::generate('sub');
        $this->customer = $customer;
        $this->product = $product;
        $this->status = $status;
        $this->startedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): string { return $this->id; }
    public function getCustomer(): Customer { return $this->customer; }
    public function getProduct(): Product { return $this->product; }
    public function getPlanId(): string { return $this->product->getPlanId(); }
    public function getGroup(): string { return $this->product->getGroup(); }
    public function getProviderSubscriptionId(): ?string { return $this->providerSubscriptionId; }
    public function getProviderScheduleId(): ?string { return $this->providerScheduleId; }
    public function getStatus(): string { return $this->status; }
    public function isCancelAtPeriodEnd(): bool { return $this->cancelAtPeriodEnd; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getTrialEndsAt(): ?\DateTimeImmutable { return $this->trialEndsAt; }
    public function getCurrentPeriodStartAt(): ?\DateTimeImmutable { return $this->currentPeriodStartAt; }
    public function getCurrentPeriodEndAt(): ?\DateTimeImmutable { return $this->currentPeriodEndAt; }
    public function getCanceledAt(): ?\DateTimeImmutable { return $this->canceledAt; }
    public function getEndedAt(): ?\DateTimeImmutable { return $this->endedAt; }
    public function getScheduledPlanId(): ?string { return $this->scheduledPlanId; }
    public function getQuantity(): int { return $this->quantity; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function hasProviderSubscription(): bool { return null !== $this->providerSubscriptionId; }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return \in_array($this->status, self::ACTIVE_STATUSES, true) && (null === $this->endedAt || $this->endedAt > $now);
    }

    public function syncFromProvider(
        string $providerSubscriptionId,
        string $status,
        bool $cancelAtPeriodEnd,
        ?\DateTimeImmutable $periodStart,
        ?\DateTimeImmutable $periodEnd,
        ?\DateTimeImmutable $canceledAt,
        ?\DateTimeImmutable $trialEndsAt,
        ?string $scheduleId,
        \DateTimeImmutable $now,
    ): void {
        $this->providerSubscriptionId = $providerSubscriptionId;
        $this->status = $status;
        $this->cancelAtPeriodEnd = $cancelAtPeriodEnd;
        $this->currentPeriodStartAt = $periodStart ?? $this->currentPeriodStartAt;
        $this->currentPeriodEndAt = $periodEnd ?? $this->currentPeriodEndAt;
        $this->canceledAt = $canceledAt;
        $this->trialEndsAt = $trialEndsAt ?? $this->trialEndsAt;
        $this->providerScheduleId = $scheduleId;
        $this->updatedAt = $now;
    }

    public function schedule(?string $planId, bool $cancelAtPeriodEnd, \DateTimeImmutable $now, ?string $scheduleId = null): void
    {
        $this->scheduledPlanId = $planId;
        $this->cancelAtPeriodEnd = $cancelAtPeriodEnd;
        $this->canceledAt = $cancelAtPeriodEnd ? $now : null;
        $this->providerScheduleId = $scheduleId;
        $this->updatedAt = $now;
    }

    public function clearSchedule(\DateTimeImmutable $now): void
    {
        $this->schedule(null, false, $now);
    }

    public function end(\DateTimeImmutable $now, string $status = 'ended'): void
    {
        $this->status = $status;
        $this->endedAt = $now;
        $this->updatedAt = $now;
    }
}
