---
title: Architecture
description: Où se trouve chaque partie du bundle.
sidebar:
  order: 5
---

```text
.---------------------------.      .---------------------------.
|  Vos contrôleurs, Twig    |      |  Stripe (webhooks reçus)  |
'-------------+-------------'      '-------------+-------------'
              |                                  |
              v                                  v
.-------------+-------------.      .-------------+-------------.
|  Façade PayKit, API       |      |    WebhookController      |
|  attribut, voter          |      |    + WebhookProcessor     |
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
|  (Doctrine)               |      |  Stripe / Fake / le vôtre |
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

| Répertoire | Contenu |
|---|---|
| `src/Catalog/` | définitions des offres et des fonctionnalités, construites à partir de la configuration |
| `src/Entity/` | entités Doctrine (tables `paykit_*`) |
| `src/Repository/` | `BillingRepository` : toutes les requêtes dans un seul adaptateur |
| `src/Service/` | `SubscriptionService`, `EntitlementService`, `CustomerService`, `ProductSyncService`, `WebhookProcessor` |
| `src/Provider/` | `PaymentProviderInterface`, ses DTO, et `Stripe/StripeProvider` |
| `src/Security/` | `RequiresFeature` et son écouteur, `FeatureVoter`, résolveurs de client |
| `src/Twig/` | `PayKitExtension` |
| `src/Controller/` | `WebhookController`, `ApiController` |
| `src/Command/` | `paykit:push`, `paykit:status` |
| `src/Event/` | événements Symfony |
| `src/Testing/` | `FakeProvider` |
| `src/PayKit.php` | la façade |
| `migrations/` | la migration Doctrine portable |
| `config/routes.php` | routes du webhook et de l'API |
