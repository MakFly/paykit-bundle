<?php

declare(strict_types=1);

namespace PayKit\Bundle\Event;

use PayKit\Bundle\Model\ReportResult;
use Symfony\Contracts\EventDispatcher\Event;

final class UsageReportedEvent extends Event
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $featureId,
        public readonly int $amount,
        public readonly ReportResult $result,
    ) {
    }
}
