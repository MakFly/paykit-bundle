---
title: Custom providers
description: Replace Stripe with another payment provider.
sidebar:
  order: 9
---

The bundle talks to payments through one port, `PaymentProviderInterface`. Stripe is
the adapter shipped with it. To use another provider, implement the interface and
alias it:

```yaml
services:
    PayKit\Bundle\Provider\PaymentProviderInterface: '@App\Billing\PaddleProvider'
```

The interface covers:

| Area | Methods |
|---|---|
| Customers | `createCustomer`, `updateCustomer`, `deleteCustomer` |
| Catalog | `syncProduct` |
| Checkout | `createCheckout` |
| Subscriptions | `createSubscription`, `updateSubscription`, `scheduleSubscriptionChange`, `cancelSubscription`, `resumeSubscription` |
| Portal | `createPortalSession` |
| Webhooks | `parseWebhook` |

Data crosses the port as DTOs from `PayKit\Bundle\Provider\Dto`
(`CheckoutRequest`, `ProviderSubscription`, `ProviderEvent`...).
`StripeProvider` and `FakeProvider` are two complete implementations to read before
writing your own.
