<?php

declare(strict_types=1);

namespace PayKit\Bundle\Provider\Dto;

final readonly class ProviderPaymentMethod
{
    public function __construct(
        public string $id,
        public string $type,
        public ?string $brand = null,
        public ?string $last4 = null,
        public ?int $expiryMonth = null,
        public ?int $expiryYear = null,
        public bool $isDefault = false,
    ) {
    }
}
