<?php

declare(strict_types=1);

namespace PayKit\Bundle\Provider\Dto;

final readonly class CheckoutRequest
{
    /** @param array<string, string> $metadata */
    public function __construct(
        public string $providerCustomerId,
        public string $priceId,
        public string $successUrl,
        public ?string $cancelUrl = null,
        public array $metadata = [],
        public ?int $trialDays = null,
    ) {
    }
}
