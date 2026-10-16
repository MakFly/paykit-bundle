---
title: Vous venez de PayKit (TypeScript)
description: Comment l'API TypeScript de PayKit se transpose dans ce bundle, et ce qui n'est pas encore porté.
sidebar:
  order: 4
---

| PayKit TS | Ce bundle |
|---|---|
| `feature({ id, type })` | `paykit.features.<id>: boolean\|metered` |
| `plan({ id, group, default, price, includes })` | `paykit.plans.<id>: { group, default, price, includes }` |
| `messages({ limit, reset })` | `includes: { messages: { limit, reset } }` |
| `createPayKit({ stripe, database, products })` | configuration du bundle et la connexion Doctrine |
| `paykit.subscribe({...})` | `PayKit::subscribe()` qui renvoie `SubscribeResult` |
| `paykit.check()` / `paykit.report()` | `PayKit::check()` / `can()` / `report()` |
| `paykit.upsertCustomer/getCustomer/listCustomers/deleteCustomer` | les mêmes noms sur `PayKit` |
| `paykit.customerPortal()` | `PayKit::customerPortal()` |
| `paykit.handleWebhook()` / `/paykit/webhook` | `PayKit::handleWebhook()` / `WebhookController` |
| `identify(request)` | `CustomerResolverInterface` / `BillableInterface` |
| `on: { "customer.updated" }` | `CustomerUpdatedEvent`, plus des événements plus fins |
| `createPayKitClient()` | routes JSON sous `/paykit/api` |
| `trustedOrigins`, `basePath` | `paykit.trusted_origins`, `prefix` de l'import de routes |
| `paykitjs push` / `status` | `bin/console paykit:push` / `paykit:status` |
| Drizzle migrations | migration Doctrine `PayKit\Bundle\Migrations\Version20261016000000` |
| `PaymentProvider` (Stripe) | `PaymentProviderInterface` et `StripeProvider` |
| test clocks | `FakeProvider` et le `MockClock` de Symfony |

Propre à ce bundle : `#[RequiresFeature]`, le voter `PAYKIT_FEATURE` et les
fonctions Twig `paykit_can`, `paykit_balance` et `paykit_plan`.

## Pas encore pris en charge

- Le tableau de bord PayKit (`@paykitjs/dash`) et `paykitjs listen` (utilisez `stripe listen`).
- Les aides pour les test clocks Stripe, les jeux de prix multi-devises, et la facturation à la quantité ou au siège.
- La génération d'un client JS typé.
