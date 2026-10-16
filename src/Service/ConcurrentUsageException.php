<?php

declare(strict_types=1);

namespace PayKit\Bundle\Service;

use PayKit\Bundle\Exception\PayKitException;

/** Thrown when a concurrent report() consumed the balance between read and deduction; safe to retry. */
final class ConcurrentUsageException extends PayKitException
{
    public function __construct()
    {
        parent::__construct('CONCURRENT_USAGE', 'Balance changed concurrently, retry the report.');
    }
}
