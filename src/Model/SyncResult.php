<?php

declare(strict_types=1);

namespace PayKit\Bundle\Model;

final readonly class SyncResult
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const UNCHANGED = 'unchanged';

    public function __construct(public string $planId, public string $action, public int $version)
    {
    }
}
