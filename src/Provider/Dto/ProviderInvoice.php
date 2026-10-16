<?php

declare(strict_types=1);

namespace PayKit\Bundle\Provider\Dto;

final readonly class ProviderInvoice
{
    public function __construct(
        public string $id,
        public string $status,
        public int $total,
        public string $currency,
        public ?string $hostedUrl = null,
        public ?string $subscriptionId = null,
        public ?\DateTimeImmutable $periodStart = null,
        public ?\DateTimeImmutable $periodEnd = null,
    ) {
    }
}
