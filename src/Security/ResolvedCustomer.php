<?php

declare(strict_types=1);

namespace PayKit\Bundle\Security;

final readonly class ResolvedCustomer
{
    public function __construct(
        public string $id,
        public ?string $email = null,
        public ?string $name = null,
    ) {
    }
}
