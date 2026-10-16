---
title: Webhooks
description: How the bundle receives Stripe events, and why each one is applied exactly once.
sidebar:
  order: 5
---

Stripe tells your app what happened (a Checkout completed, a renewal was paid, a
schedule switched plans) through webhooks. The bundle exposes one endpoint for them:

```
POST /paykit/webhook
```

The path follows the `prefix` of the route import.

## Stripe setup

Create an endpoint pointing at `https://your-app.example/paykit/webhook` and subscribe
it to:

- `checkout.session.completed`
- `customer.subscription.*`
- `invoice.*`
- `payment_method.attached` and `payment_method.detached`

Copy its signing secret into `STRIPE_WEBHOOK_SECRET`. Requests with an invalid signature
are rejected.

For local development:

```bash
stripe listen --forward-to localhost:8000/paykit/webhook
```

`stripe listen` prints a temporary signing secret: use it as `STRIPE_WEBHOOK_SECRET`
while it runs.

## Idempotency

Stripe retries deliveries and can send the same event twice. Each event is recorded
before it is applied:

- an event is **claimed once**; a second delivery is counted as a duplicate and skipped
- a failure is recorded, and the event is applied again on Stripe's next retry
- `WebhookProcessedEvent` is dispatched only for events actually applied

The endpoint answers `{ received, processed, duplicates }`.

## Handling webhooks yourself

To receive webhooks from your own controller, call the façade:

```php
$summary = $payKit->handleWebhook($request->getContent(), $request->headers->all());
```
