---
title: HTTP API
description: JSON routes for a JavaScript frontend, and the rules that keep them safe.
sidebar:
  order: 6
---

A single-page app can drive billing through JSON routes under the route prefix:

| Route | Body | Returns |
|---|---|---|
| `POST /paykit/api/subscribe` | `{ planId, successUrl?, cancelUrl?, forceCheckout? }` | `{ action, paymentUrl, invoice, requiredAction }` |
| `POST /paykit/api/customer-portal` | `{ returnUrl }` | `{ url }` |
| `GET /paykit/api/customer` | | customer, subscriptions, entitlements |
| `POST /paykit/webhook` | Stripe payload | `{ received, processed, duplicates }` |

```ts
const response = await fetch('/paykit/api/subscribe', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ planId: 'pro', successUrl: '/billing?upgraded=1' }),
});
const result = await response.json();
if (result.paymentUrl) window.location.assign(result.paymentUrl);
```

## Security rules

- **The customer always comes from the [resolver](/guides/access-control/#customer-resolution).**
  A `customerId` in the body that does not match it gets a 403.
- **Cross-origin POSTs are rejected** unless the origin is listed in
  `paykit.trusted_origins`. Add your frontend's origin only when it is served from
  another domain than the API.
- **Relative return URLs** are resolved against the request origin.

```yaml
paykit:
    trusted_origins: ['https://app.example.com']
```
