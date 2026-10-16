---
title: Tests
description: Exécutez votre suite de tests et le développement local sans Stripe.
sidebar:
  order: 8
---

## Le fournisseur fictif

Basculez le fournisseur dans l'environnement de test :

```yaml
when@test:
    paykit:
        provider: fake
```

`PayKit\Bundle\Testing\FakeProvider` garde tout en mémoire :

- il enregistre chaque appel, lisible avec `calledMethods()`
- il renvoie de fausses URL de Checkout et de portail de facturation
- il vous laisse pousser des événements webhook avec `queueWebhook()`, livrés à
  `/paykit/webhook` avec l'en-tête `X-Fake-Signature: valid`

```php
$provider = static::getContainer()->get(PaymentProviderInterface::class);
\assert($provider instanceof FakeProvider);

$payKit->upsertCustomer('user_1', 'user@example.test');
$payKit->subscribe('user_1', 'pro', successUrl: 'https://app.test/billing');
self::assertContains('createCheckout', $provider->calledMethods());

// Simulate Stripe confirming the payment
$provider->queueWebhook($payload, ...$events);
$payKit->handleWebhook($payload, ['X-Fake-Signature' => 'valid']);
```

Une descente est planifiée par le fournisseur fictif, mais rien ne l'applique en
arrière-plan : livrez vous-même le webhook correspondant pour simuler la fin de
la période.

## Réinitialisation des périodes

Les soldes se réinitialisent par rapport au service `clock`. Remplacez-le par
le `MockClock` de Symfony avant le premier appel, puis avancez le temps :

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-01-01 00:00:00', 'UTC');
static::getContainer()->set('clock', $clock);

// ... consume some usage
$clock->modify('+1 month');
// the next check() or report() starts a fresh period
```

La suite de tests du bundle (`tests/PayKitTestCase.php` dans le dépôt) utilise
cette configuration et constitue un bon point de départ.

## PayKit Playground

Le dépôt fournit un exemple interactif dans `examples/fullstack` : un monorepo
Bun avec React, Vite, shadcn/ui et une API Symfony liée au bundle local. Il
tourne sur SQLite avec le fournisseur fictif, sans identifiants Stripe. Il vit
uniquement dans le dépôt Git : il ne fait pas partie du paquet Composer.

Il contient deux applications complètes, chacune dans un module d'API avec son
propre groupe de forfaits : Établi, une boutique dont l'adhésion Club débloque
la livraison offerte, les prix membres et des crédits de livraison express, et
Plume, un assistant d'écriture en SaaS où chaque réécriture consomme un message
du quota et où le modèle Expert demande un forfait Pro.
