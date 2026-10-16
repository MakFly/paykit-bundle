---
title: Architecture
description: Where each part of the bundle lives.
sidebar:
  order: 5
---

```text
.---------------------------.      .---------------------------.
|  Your controllers, Twig   |      |   Stripe (webhooks in)    |
'-------------+-------------'      '-------------+-------------'
              |                                  |
              v                                  v
.-------------+-------------.      .-------------+-------------.
|   PayKit facade, API,     |      |    WebhookController      |
|   attribute, voter        |      |    + WebhookProcessor     |
'-------------+-------------'      '-------------+-------------'
              |                                  |
              v                                  v
.-------------+----------------------------------+-------------.
|  Services: Subscription, Entitlement, Customer, ProductSync  |
'-------------+----------------------------------+-------------'
              |                                  |
              v                                  v
.-------------+-------------.      .-------------+-------------.
|  BillingRepository        |      |  PaymentProviderInterface |
|  (Doctrine)               |      |  Stripe / Fake / yours    |
'-------------+-------------'      '---------------------------'
              |
              v
       .------+-------.
      (                )
      |'--------------'|
      |  paykit_*      |
      |  tables        |
      '----------------'
```

| Directory | Contents |
|---|---|
| `src/Catalog/` | plan and feature definitions, built from the configuration |
| `src/Entity/` | Doctrine entities (`paykit_*` tables) |
| `src/Repository/` | `BillingRepository`: every query in one adapter |
| `src/Service/` | `SubscriptionService`, `EntitlementService`, `CustomerService`, `ProductSyncService`, `WebhookProcessor` |
| `src/Provider/` | `PaymentProviderInterface`, its DTOs, and `Stripe/StripeProvider` |
| `src/Security/` | `RequiresFeature` and its listener, `FeatureVoter`, customer resolvers |
| `src/Twig/` | `PayKitExtension` |
| `src/Controller/` | `WebhookController`, `ApiController` |
| `src/Command/` | `paykit:push`, `paykit:status` |
| `src/Event/` | Symfony events |
| `src/Testing/` | `FakeProvider` |
| `src/PayKit.php` | the façade |
| `migrations/` | the portable Doctrine migration |
| `config/routes.php` | webhook and API routes |
