<?php

declare(strict_types=1);

namespace PayKit\Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'paykit_invoice')]
#[ORM\UniqueConstraint(name: 'paykit_invoice_provider_unique', columns: ['provider_invoice_id'])]
#[ORM\Index(name: 'paykit_invoice_customer_idx', columns: ['customer_id'])]
class Invoice
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 40)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: false)]
    private Customer $customer;

    #[ORM\Column(name: 'subscription_id', length: 40, nullable: true)]
    private ?string $subscriptionId = null;

    #[ORM\Column(name: 'provider_invoice_id', length: 255)]
    private string $providerInvoiceId;

    #[ORM\Column(name: 'status', length: 32)]
    private string $status;

    #[ORM\Column(name: 'amount')]
    private int $amount;

    #[ORM\Column(name: 'currency', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'hosted_url', type: Types::TEXT, nullable: true)]
    private ?string $hostedUrl = null;

    #[ORM\Column(name: 'period_start_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $periodStartAt = null;

    #[ORM\Column(name: 'period_end_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $periodEndAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Customer $customer, string $providerInvoiceId, \DateTimeImmutable $now)
    {
        $this->id = Id::generate('inv');
        $this->customer = $customer;
        $this->providerInvoiceId = $providerInvoiceId;
        $this->status = 'draft';
        $this->amount = 0;
        $this->currency = 'usd';
        $this->createdAt = $now;
    }

    public function update(string $status, int $amount, string $currency, ?string $hostedUrl, ?string $subscriptionId, ?\DateTimeImmutable $periodStart, ?\DateTimeImmutable $periodEnd): void
    {
        $this->status = $status;
        $this->amount = $amount;
        $this->currency = strtolower($currency);
        $this->hostedUrl = $hostedUrl;
        $this->subscriptionId = $subscriptionId ?? $this->subscriptionId;
        $this->periodStartAt = $periodStart;
        $this->periodEndAt = $periodEnd;
    }

    public function getId(): string { return $this->id; }
    public function getCustomer(): Customer { return $this->customer; }
    public function getSubscriptionId(): ?string { return $this->subscriptionId; }
    public function getProviderInvoiceId(): string { return $this->providerInvoiceId; }
    public function getStatus(): string { return $this->status; }
    public function getAmount(): int { return $this->amount; }
    public function getCurrency(): string { return $this->currency; }
    public function getHostedUrl(): ?string { return $this->hostedUrl; }
    public function getPeriodStartAt(): ?\DateTimeImmutable { return $this->periodStartAt; }
    public function getPeriodEndAt(): ?\DateTimeImmutable { return $this->periodEndAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
