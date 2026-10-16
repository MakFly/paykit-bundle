<?php

declare(strict_types=1);

namespace PayKit\Bundle\Event;

use PayKit\Bundle\Entity\Subscription;
use Symfony\Contracts\EventDispatcher\Event;

final class SubscriptionActivatedEvent extends Event
{
    public function __construct(public readonly Subscription $subscription, public readonly ?Subscription $previous = null)
    {
    }
}
