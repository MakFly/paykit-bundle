<?php

declare(strict_types=1);

namespace PayKit\Bundle\Event;

use PayKit\Bundle\Entity\Subscription;
use Symfony\Contracts\EventDispatcher\Event;

final class SubscriptionEndedEvent extends Event
{
    public function __construct(public readonly Subscription $subscription)
    {
    }
}
