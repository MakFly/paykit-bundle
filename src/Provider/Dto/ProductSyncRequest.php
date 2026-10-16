<?php

declare(strict_types=1);

namespace PayKit\Bundle\Provider\Dto;

final readonly class ProductSyncRequest
{
    public function __construct(
        public string $planId,
        public int $version,
        public string $name,
        public int $priceAmount,
        public string $priceCurrency,
        public string $priceInterval,
        public ?string $existingProductId = null,
        public ?string $existingPriceId = null,
    ) {
    }
}
