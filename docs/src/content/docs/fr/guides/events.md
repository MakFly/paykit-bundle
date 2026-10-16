---
title: Événements
description: Réagissez aux changements d'offre, à l'usage et aux webhooks avec des écouteurs d'événements Symfony.
sidebar:
  order: 7
---

Chaque changement distribue un événement Symfony depuis l'espace de noms `PayKit\Bundle\Event`.

| Événement | Quand | Propriétés |
|---|---|---|
| `CustomerUpdatedEvent` | après tout changement d'abonnement ou de droit | `customerId`, `subscriptions` |
| `SubscriptionActivatedEvent` | une offre est devenue active | `subscription`, `previous` (celle qui est remplacée, ou `null`) |
| `SubscriptionScheduledEvent` | une descente ou une résiliation a été planifiée, ou annulée | `subscription`, `targetPlanId` (`null` quand elle est annulée) |
| `SubscriptionEndedEvent` | un abonnement s'est terminé | `subscription` |
| `WebhookProcessedEvent` | un événement du fournisseur a été appliqué (jamais pour un doublon) | `event` |
| `UsageReportedEvent` | après `report()` | `customerId`, `featureId`, `amount`, `result` |

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

`CustomerUpdatedEvent` correspond au hook `customer.updated` de PayKit. Les
autres événements sont des ajouts plus fins de ce bundle.
