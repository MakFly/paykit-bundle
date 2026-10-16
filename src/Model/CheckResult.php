<?php

declare(strict_types=1);

namespace PayKit\Bundle\Model;

final readonly class CheckResult
{
    public function __construct(public bool $allowed, public ?Balance $balance)
    {
    }
}
