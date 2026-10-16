<?php

declare(strict_types=1);

namespace PayKit\Bundle\Event;

use PayKit\Bundle\Provider\Dto\ProviderEvent;
use Symfony\Contracts\EventDispatcher\Event;

/** Fired once per provider event after local state was synced (never for duplicates). */
final class WebhookProcessedEvent extends Event
{
    public function __construct(public readonly ProviderEvent $event)
    {
    }
}
