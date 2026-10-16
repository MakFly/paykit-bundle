---
title: Subscriptions
description: One subscribe() call handles new plans, upgrades, downgrades, cancellations and resumes.
sidebar:
  order: 2
---

`subscribe()` takes the customer, the target plan and where to send the customer back
after a Stripe Checkout. It decides what the change means and returns a
`SubscribeResult` whose `action` tells you what happened.

```php
use PayKit\Bundle\Model\SubscribeAction;

$result = $payKit->subscribe($customerId, 'pro', successUrl: $ok, cancelUrl: $back);

match ($result->action) {
    SubscribeAction::Checkout  => $this->redirect($result->paymentUrl ?? $result->requiredActionUrl),
    SubscribeAction::Activated, SubscribeAction::Upgraded => $this->addFlash('success', 'Welcome to Pro!'),
    SubscribeAction::Scheduled => $this->addFlash('info', 'Your plan changes at the end of the period.'),
    SubscribeAction::Resumed, SubscribeAction::Noop => null,
};
```

## What each change does

| Change | Action | Effect |
|---|---|---|
| free to paid, card on file | `Activated` | Stripe subscription created and charged |
| free to paid, no card (or `forceCheckout: true`) | `Checkout` | redirect to `paymentUrl`; the plan activates on the webhook |
| pro to ultra | `Upgraded` | immediate and prorated (`Checkout` without a card) |
| ultra to pro | `Scheduled` | switches at period end through a Stripe subscription schedule |
| pro to free | `Scheduled` | `cancel_at_period_end`, then falls back to the free plan |
| pro with a pending change, to pro | `Resumed` | the pending change is cancelled |
| same plan | `Noop` | nothing |

A downgrade keeps the customer's current access until the end of the paid period.
Calling `subscribe()` again with the current plan cancels the pending change.

## Checkout and webhooks

When the action is `Checkout`, the plan is **not active yet**. It becomes active when
Stripe sends `checkout.session.completed`. Do not grant access from the success URL:
read the customer's state, which the webhook updates. See [Webhooks](/guides/webhooks/).

## Customers

```php
$payKit->upsertCustomer($user->getId(), $user->getEmail(), $user->getName()); // puts new customers on default plans
$view = $payKit->getCustomer($user->getId());   // ->subscriptions, ->entitlements['messages']->remaining
$page = $payKit->listCustomers(limit: 50, offset: 0, planIds: ['pro']);
$payKit->deleteCustomer($id);                   // cancels Stripe subscriptions immediately
$url  = $payKit->customerPortal($id, returnUrl: 'https://app.example.com/billing');
```

`customerPortal()` returns a Stripe Billing Portal URL where the customer manages cards
and invoices.
