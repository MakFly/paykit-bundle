<?php

declare(strict_types=1);

namespace PayKit\Bundle\Provider\Dto;

final readonly class CheckoutResult
{
    public function __construct(public string $sessionId, public string $url)
    {
    }
}
