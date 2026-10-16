# PayKit analysis

Notes from studying PayKit before porting it to Symfony.

- Website / docs: https://paykit.sh, https://paykit.sh/docs
- Source: https://github.com/getpaykit/paykit (studied at commit `c7572ff`, 2026-09-27, npm package `paykitjs` v0.2.0)
- Tagline: "Build Stripe billing without the glue code. Define plans in code. Gate features. Track usage. Webhooks handled for you." Announced as "the first billing framework for TypeScript".
- License: **MIT** (Copyright (c) 2026 Max Katz). We can reimplement it freely. We keep attribution (README + LICENSE notice) and copy no source code verbatim. The PHP port is a clean-room reimplementation of the concepts and public behavior.

## 1. What PayKit is

An **embedded** billing framework. It is not a hosted billing platform. It runs inside your app, keeps billing state in **your own database** (PostgreSQL via Drizzle), and drives Stripe for you. Its pitch sits between the two usual options:

- low-level provider SDKs, which need lots of glue code (webhook plumbing, state mirroring, proration rules), and
- hosted platforms (e.g. Autumn, Orb), which own your billing data and sit between you and your customers.

## 2. Core concepts

| Concept | PayKit (TS) | Notes |
|---|---|---|
| Feature | `feature({ id, type: "boolean" \| "metered" })` | Ids are `[a-z0-9_-]{1,64}`. |
| Feature grant | `messages({ limit, reset })` / `proModels()` | Metered grants take `limit` (positive int) and `reset` (`day/week/month/year`). Boolean grants take no config. |
| Plan | `plan({ id, name?, group?, default?, price?: { amount, interval: month\|year }, includes })` | `amount` is in major units (19 = $19). Name defaults to a title-cased id. Plans in the same **group** exclude each other. One **default** (free) plan per group. |
| Product (stored) | `paykit_product` rows | Each plan is snapshotted as a **versioned** product with a content **hash**. If price or features change, `push` creates version N+1 and old versions are never modified. A rename alone updates in place. Each product maps to a Stripe product and price. |
| Customer | `upsertCustomer({ id, email, name, metadata })` | The id is your user or org id. The Stripe customer id is internal and created lazily. New customers are auto-subscribed to the default plans. |
| Subscription | `subscribe({ customerId, planId, successUrl, cancelUrl, forceCheckout })` | One method covers the whole lifecycle (see §3). Returns `{ paymentUrl, invoice, requiredAction }`. |
| Entitlement | `check({ customerId, featureId, required? })` → `{ allowed, balance }` | Derived from the features of active subscriptions (`active`/`trialing`). Balance = `{ limit, remaining, resetAt, unlimited }`. Grants **stack** across subscriptions; any unlimited grant makes the feature unlimited. |
| Usage metering | `report({ customerId, featureId, amount })` → `{ success, balance }` | Atomic deduction (a single SQL CTE). Fails without deducting when the balance is insufficient. **Lazy resets**: no cron. On read or write, stale balances are refilled and `nextResetAt` is rolled forward from the period boundary. |
| Invoices / payment methods | `paykit_invoice`, `paykit_payment_method` | Mirrored from webhooks. A default payment method decides direct subscription vs. checkout. |
| Events | `on: { "customer.updated": fn, "*": fn }` | Fired after local state is synced, never twice for the same webhook. |
| Plugins | `plugins: [{ id, endpoints }]` | Extra HTTP endpoints, e.g. the dashboard (`@paykitjs/dash`). |
| Testing | `testing: { enabled: true }` | Stripe test clocks (`getTestClock`, `advanceTestClock`) for time travel. |

## 3. Subscription lifecycle (behavior of `subscribe()`)

Decision tree from `subscription.service.ts`, as documented:

1. **Same plan.** If a downgrade or cancellation is pending, PayKit **resumes**: it clears the scheduled change and un-cancels in Stripe. Otherwise it is a **no-op**.
2. **No active subscription in the group.** A free target activates immediately with no Stripe call. A paid target creates the Stripe subscription directly when the customer has a default payment method. Otherwise it returns a Stripe Checkout `paymentUrl`, and the plan activates on the `checkout.session.completed` webhook.
3. **The active subscription is local only (a free plan).** PayKit switches immediately, using the same rules as step 2.
4. **Target is free (cancel to free).** PayKit sets Stripe `cancel_at_period_end`. When the period ends, the deletion webhook moves the customer to free. There is no separate `cancel()`: "subscribing to the free plan is the cancellation path".
5. **Paid currency change.** PayKit uses a new checkout-backed subscription.
6. **Downgrade (cheaper paid plan).** The change is scheduled at period end with a Stripe subscription schedule, and the customer keeps the current plan until then. Picking another plan **replaces** the pending target. Only one pending change per group.
7. **Upgrade.** Applied immediately with proration, or through checkout when no payment method is on file. When the checkout completes, the old Stripe subscription is canceled.

## 4. Webhooks

`POST {basePath}/webhook` (default `/paykit/webhook`):

1. Verify the Stripe signature.
2. Normalize the Stripe event into provider-neutral events: `checkout.completed`, `subscription.updated`, `subscription.deleted`, `invoice.updated`, `payment.succeeded`, `payment_method.attached` / `detached`. These come with "apply actions" (upsert/delete of payment methods, invoices, subscriptions).
3. **Idempotency.** Each event is claimed in `paykit_webhook_event` (unique `stripe_event_id`, status, error, trace id). Duplicates are skipped. Failures are recorded and retried on redelivery. Claim ownership is enforced (v0.2.0).
4. Apply state changes in the DB, then emit `customer.updated`.

CLI `paykitjs listen` tunnels Stripe webhooks to localhost and replays missed events.

## 5. Checkout and portal

- Stripe Checkout (subscription mode) for first payments, upgrades without a payment method, and currency changes. The pending intent is stored in `paykit_metadata` keyed by checkout session id.
- `customerPortal({ returnUrl })` returns a Stripe Billing Portal URL.
- Return URLs can be relative. They resolve against the request origin or `trustedOrigins`, which are hardened in v0.2.0.

## 6. Database

Drizzle schema with tables prefixed `paykit_`: `customer`, `payment_method`, `feature`, `product` (versioned, hash, Stripe ids), `product_feature`, `subscription` (status, cancel flags, period dates, `scheduled_product_id`, Stripe schedule id), `entitlement` (limit, balance, next_reset_at), `invoice`, `metadata`, `webhook_event`. SQL migrations ship with the package and run via `paykitjs push`.

## 7. Server / client API and architecture

- `createPayKit(options)` returns an instance exposing the methods `subscribe`, `customerPortal`, `upsertCustomer`, `getCustomer`, `deleteCustomer`, `listCustomers`, `check`, `report`, `handleWebhook` (+ testing methods).
- Every method is defined once (`definePayKitMethod`, zod input). Some are flagged `client: true` and exposed over HTTP through a `better-call` router at `{basePath}/api/*`.
- `identify(request)` resolves the authenticated customer. It is the **trust boundary**: a `customerId` sent by the client must match it.
- `createPayKitClient<typeof paykit>()` is a type-safe browser client exposing `subscribe` and `customerPortal`. Plan ids are type-checked.
- Framework handlers: Next.js, Hono, Express, etc. (`paykit.handler`).
- Internals: `PayKitContext` (db, provider, logger with trace ids, products), a `PaymentProvider` interface (Stripe is the only implementation today; PayKit is "Stripe-only" since 0.1.x, but the abstraction remains), services per domain, typed error codes.
- CLI: `init` (scaffolding), `push` (migrations + plan sync, `-y` for CI), `status` (config/DB/Stripe/sync checks, `--throw`), `listen`.
- Dashboard: `@paykitjs/dash`, a React SPA served under `{basePath}`.

## 8. Porting decisions (TypeScript → Symfony)

| PayKit | Symfony port |
|---|---|
| `feature()` / `plan()` in `paykit.ts` | `paykit.features` / `paykit.plans` in YAML, validated by the Config component and `Catalog` at container compile time |
| zod validation | Config tree + value-object constructors |
| Drizzle schema + SQL migrations | Doctrine ORM attribute entities + one portable Doctrine Migration (tested against the mapping) |
| `paykitjs push` / `status` | `paykit:push [--dry-run]` / `paykit:status [--throw]` |
| `identify` | `CustomerResolverInterface` (default: security user, `BillableInterface`) |
| `on: {...}` handlers | Symfony events (`CustomerUpdatedEvent`, `Subscription*Event`, `WebhookProcessedEvent`, `UsageReportedEvent`) |
| `better-call` router + client SDK | Bundle routes (`/webhook`, `/api/subscribe`, `/api/customer-portal`, `/api/customer`) returning JSON |
| (new) | `#[RequiresFeature]` attribute, `PAYKIT_FEATURE` voter, `paykit_*` Twig functions |
| Single CTE for `report()` | Transaction + conditional `UPDATE … WHERE balance >= :amount` per grant (portable, race-safe) |
| Test clocks | `FakeProvider` + PSR-20 clock (`MockClock`) for time travel in tests |
