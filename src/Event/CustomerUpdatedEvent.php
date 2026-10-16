<?php

declare(strict_types=1);

namespace PayKit\Bundle\Event;

use PayKit\Bundle\Entity\Subscription;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired after any subscription or entitlement change for a customer (PayKit's "customer.updated").
 */
final class CustomerUpdatedEvent extends Event
{
    /** @param list<Subscription> $subscriptions current (non-ended) subscriptions */
    public function __construct(
        public readonly string $customerId,
        public readonly array $subscriptions,
    ) {
    }
}
