<?php

declare(strict_types=1);

namespace PayKit\Bundle\Event;

use PayKit\Bundle\Entity\Subscription;
use Symfony\Contracts\EventDispatcher\Event;

/** A downgrade / cancel-to-free was scheduled (targetPlanId) or a pending change was cleared (null). */
final class SubscriptionScheduledEvent extends Event
{
    public function __construct(public readonly Subscription $subscription, public readonly ?string $targetPlanId)
    {
    }
}
