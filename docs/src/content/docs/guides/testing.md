---
title: Testing
description: Run your test suite and local development without Stripe.
sidebar:
  order: 8
---

## The fake provider

Switch the provider in the test environment:

```yaml
when@test:
    paykit:
        provider: fake
```

`PayKit\Bundle\Testing\FakeProvider` keeps everything in memory:

- it records each call, readable with `calledMethods()`
- it returns fake Checkout and Billing Portal URLs
- it lets you push webhook events with `queueWebhook()`, delivered to
  `/paykit/webhook` with the header `X-Fake-Signature: valid`

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

A downgrade is scheduled by the fake provider but nothing applies it in the
background: deliver the matching webhook yourself to simulate the end of the period.

## Period resets

Balances reset against the `clock` service. Replace it with Symfony's `MockClock`
before the first call, then move time forward:

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-01-01 00:00:00', 'UTC');
static::getContainer()->set('clock', $clock);

// ... consume some usage
$clock->modify('+1 month');
// the next check() or report() starts a fresh period
```

The bundle's own test suite (`tests/PayKitTestCase.php` in the repository) uses this
setup and is a good starting point.

## PayKit Playground

The repository ships an interactive example in `examples/fullstack`: a Bun monorepo
with React, Vite, shadcn/ui and a Symfony API linked to the local bundle. It runs on
SQLite with the fake provider, without Stripe credentials. It lives in the Git
repository only; it is not part of the Composer package.

It ships two complete demo apps, each a separate API module with its own plan
group: Établi, an online store whose Club membership unlocks free shipping,
member prices and express delivery credits, and Plume, a writing assistant SaaS
where each rewrite uses a message from the quota and the Expert model needs Pro.
