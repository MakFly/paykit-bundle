<?php

declare(strict_types=1);

namespace PayKit\Bundle\Provider\Dto;

final readonly class ProviderSubscription
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $priceId = null,
        public bool $cancelAtPeriodEnd = false,
        public ?\DateTimeImmutable $currentPeriodStart = null,
        public ?\DateTimeImmutable $currentPeriodEnd = null,
        public ?\DateTimeImmutable $canceledAt = null,
        public ?\DateTimeImmutable $endedAt = null,
        public ?\DateTimeImmutable $trialEnd = null,
        public ?string $scheduleId = null,
        public ?ProviderInvoice $latestInvoice = null,
        public ?string $paymentActionUrl = null,
    ) {
    }
}
