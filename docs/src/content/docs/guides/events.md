---
title: Events
description: React to plan changes, usage and webhooks with Symfony event listeners.
sidebar:
  order: 7
---

Every change dispatches a Symfony event from the `PayKit\Bundle\Event` namespace.

| Event | When | Properties |
|---|---|---|
| `CustomerUpdatedEvent` | after any subscription or entitlement change | `customerId`, `subscriptions` |
| `SubscriptionActivatedEvent` | a plan became active | `subscription`, `previous` (the replaced one, or `null`) |
| `SubscriptionScheduledEvent` | a downgrade or cancellation was scheduled, or cleared | `subscription`, `targetPlanId` (`null` when cleared) |
| `SubscriptionEndedEvent` | a subscription ended | `subscription` |
| `WebhookProcessedEvent` | a provider event was applied (never for duplicates) | `event` |
| `UsageReportedEvent` | after `report()` | `customerId`, `featureId`, `amount`, `result` |

```php
use PayKit\Bundle\Event\CustomerUpdatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class BillingCacheListener
{
    public function __construct(private TagAwareCacheInterface $cache) {}

    #[AsEventListener]
    public function onCustomerUpdated(CustomerUpdatedEvent $event): void
    {
        $this->cache->invalidateTags(['billing_'.$event->customerId]);
    }
}
```

`CustomerUpdatedEvent` matches PayKit's `customer.updated` hook. The other events are
finer-grained additions of this bundle.
