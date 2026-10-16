---
title: Coming from PayKit (TypeScript)
description: How PayKit's TypeScript API maps to this bundle, and what is not ported yet.
sidebar:
  order: 4
---

| PayKit TS | This bundle |
|---|---|
| `feature({ id, type })` | `paykit.features.<id>: boolean\|metered` |
| `plan({ id, group, default, price, includes })` | `paykit.plans.<id>: { group, default, price, includes }` |
| `messages({ limit, reset })` | `includes: { messages: { limit, reset } }` |
| `createPayKit({ stripe, database, products })` | bundle config and the Doctrine connection |
| `paykit.subscribe({...})` | `PayKit::subscribe()` returning `SubscribeResult` |
| `paykit.check()` / `paykit.report()` | `PayKit::check()` / `can()` / `report()` |
| `paykit.upsertCustomer/getCustomer/listCustomers/deleteCustomer` | same names on `PayKit` |
| `paykit.customerPortal()` | `PayKit::customerPortal()` |
| `paykit.handleWebhook()` / `/paykit/webhook` | `PayKit::handleWebhook()` / `WebhookController` |
| `identify(request)` | `CustomerResolverInterface` / `BillableInterface` |
| `on: { "customer.updated" }` | `CustomerUpdatedEvent`, plus finer-grained events |
| `createPayKitClient()` | JSON routes under `/paykit/api` |
| `trustedOrigins`, `basePath` | `paykit.trusted_origins`, route import `prefix` |
| `paykitjs push` / `status` | `bin/console paykit:push` / `paykit:status` |
| Drizzle migrations | Doctrine migration `PayKit\Bundle\Migrations\Version20261016000000` |
| `PaymentProvider` (Stripe) | `PaymentProviderInterface` and `StripeProvider` |
| test clocks | `FakeProvider` and Symfony `MockClock` |

Only in this bundle: `#[RequiresFeature]`, the `PAYKIT_FEATURE` voter and the Twig
functions `paykit_can`, `paykit_balance` and `paykit_plan`.

## Not supported yet

- The PayKit dashboard (`@paykitjs/dash`) and `paykitjs listen` (use `stripe listen`).
- Stripe test-clock helpers, multi-currency price sets, and quantity or seat billing.
- Typed JS client generation.
