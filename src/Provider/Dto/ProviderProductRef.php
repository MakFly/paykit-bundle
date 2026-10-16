<?php

declare(strict_types=1);

namespace PayKit\Bundle\Provider\Dto;

final readonly class ProviderProductRef
{
    public function __construct(public string $productId, public string $priceId)
    {
    }
}
