# PayKit Bundle for Symfony

**Stripe billing for Symfony without the glue code.** Declare plans in YAML, gate features, meter usage, and let the bundle handle Stripe Checkout, subscription changes and webhooks. Billing state stays in **your** database through Doctrine.

This is an independent Symfony port of [PayKit](https://paykit.sh) ([getpaykit/paykit](https://github.com/getpaykit/paykit)), "the first billing framework for TypeScript", by Max Katz and contributors. It reuses PayKit's concepts and API shape, reimplemented in idiomatic PHP. Thanks to the PayKit authors for the design. 🙏

> Status: **0.1 (experimental)**. The API may change before 1.0.

```php
$result = $payKit->subscribe($user->getId(), 'pro', successUrl: $successUrl);
if ($result->paymentUrl) {
    return $this->redirect($result->paymentUrl);       // Stripe Checkout
}

if (!$payKit->check($userId, 'messages')->allowed) {
    throw new HttpException(402, 'Usage limit reached');
}
$answer = $chat->reply($prompt);
$payKit->report($userId, 'messages');                  // consume 1 unit
```

## Features

- **Plans in config**: features (`boolean` / `metered`), plans, groups, default free plans, prices and trials, all validated when the container compiles.
- **One `subscribe()` for the whole lifecycle**: new subscriptions, immediate prorated upgrades, downgrades scheduled at period end, cancel-to-free, resume, and no-ops.
- **Entitlements**: `check()` / `can()`, a `#[RequiresFeature]` controller attribute, the `PAYKIT_FEATURE` voter, and Twig functions.
- **Usage metering**: atomic `report()`, grants that stack across plans, unlimited grants, and lazy period resets (no cron).
- **Stripe adapter**: Checkout, Billing Portal, subscription schedules, signature-verified webhooks.
- **Idempotent webhooks**: each event is claimed once, failures are recorded and retried, and duplicates are skipped.
- **Versioned plans**: `paykit:push` creates a new product version when price or features change. Existing subscribers keep their version.
- **Symfony events** for every change, and a **fake provider** for tests and offline development.

## Requirements

PHP 8.2+, Symfony 7.1+, Doctrine ORM 3, a database supported by Doctrine DBAL (PostgreSQL, MySQL/MariaDB, SQLite).

## Installation

```bash
composer require makfly/paykit-bundle
```

Without Flex, register the bundle and the routes yourself:

```php
// config/bundles.php
return [
    // ...
    PayKit\Bundle\PayKitBundle::class => ['all' => true],
];
```

```yaml
# config/routes/paykit.yaml
paykit:
    resource: '@PayKitBundle/config/routes.php'
    prefix: /paykit
```

Then create the tables and push your plans:

```bash
php bin/console doctrine:migrations:migrate   # the bundle registers its own migration
php bin/console paykit:push                   # creates Stripe products/prices + DB versions
```

Point a Stripe webhook endpoint at `https://your-app.example/paykit/webhook` and subscribe it to `checkout.session.completed`, `customer.subscription.*`, `invoice.*`, `payment_method.attached` and `payment_method.detached`. For local development:
`stripe listen --forward-to localhost:8000/paykit/webhook`.

## Configuration

```yaml
# config/packages/paykit.yaml
paykit:
    provider: stripe            # or "fake" (in-memory, for tests / offline dev)
    currency: usd
    stripe:
        secret_key: '%env(STRIPE_SECRET_KEY)%'
        webhook_secret: '%env(STRIPE_WEBHOOK_SECRET)%'
    trusted_origins: ['https://app.example.com']   # only if the frontend is on another origin
    # customer_resolver: App\Billing\OrgCustomerResolver

    features:
        messages: metered
        pro_models: boolean

    plans:
        free:
            group: base
            default: true                         # new customers land here
            includes:
                messages: { limit: 100, reset: month }
        pro:
            name: Pro                             # defaults to the id, title-cased
            group: base
            price: { amount: 19, interval: month }  # major units: 19 = $19.00
            trial_days: 14
            includes:
                messages: { limit: 2000, reset: month }
                pro_models: ~
        ultra:
            group: base
            price: { amount: 49, interval: month }
            includes:
                messages: ~                       # metered without limit = unlimited
                pro_models: ~
        extra_messages:                            # add-on: different group, so it stacks
            group: addons
            price: { amount: 5, interval: month }
            includes:
                messages: { limit: 500, reset: month }

when@test:
    paykit:
        provider: fake
```

Re-run `php bin/console paykit:push` after changing plans, and in your deploy pipeline (`paykit:push --dry-run` previews the changes; `paykit:status --throw` fails CI when plans are out of sync).

## Usage

Inject `PayKit\Bundle\PayKit` (service alias `paykit`).

### Customers

```php
$payKit->upsertCustomer($user->getId(), $user->getEmail(), $user->getName()); // puts new customers on default plans
$view = $payKit->getCustomer($user->getId());   // ->subscriptions, ->entitlements['messages']->remaining
$page = $payKit->listCustomers(limit: 50, offset: 0, planIds: ['pro']);
$payKit->deleteCustomer($id);                   // cancels Stripe subscriptions immediately
$url  = $payKit->customerPortal($id, returnUrl: 'https://app.example.com/billing');
```

### Subscriptions

```php
$result = $payKit->subscribe($customerId, 'pro', successUrl: $ok, cancelUrl: $back);

match ($result->action) {
    SubscribeAction::Checkout  => $this->redirect($result->paymentUrl ?? $result->requiredActionUrl),
    SubscribeAction::Activated, SubscribeAction::Upgraded => $this->addFlash('success', 'Welcome to Pro!'),
    SubscribeAction::Scheduled => $this->addFlash('info', 'Your plan changes at the end of the period.'),
    SubscribeAction::Resumed, SubscribeAction::Noop => null,
};
```

| Call | Result |
|---|---|
| free → paid, card on file | `Activated`: Stripe subscription created and charged |
| free → paid, no card (or `forceCheckout: true`) | `Checkout`: redirect to `paymentUrl`. The plan activates on the webhook |
| pro → ultra | `Upgraded`: immediate, prorated (or `Checkout` without a card) |
| ultra → pro | `Scheduled`: switches at period end via a Stripe subscription schedule |
| pro → free | `Scheduled`: `cancel_at_period_end`, then falls back to free |
| pro (pending change) → pro | `Resumed` |
| same plan | `Noop` |

### Entitlements and metering

```php
$check = $payKit->check($customerId, 'messages');      // CheckResult { allowed, balance }
$check->balance?->remaining;                           // Balance { limit, remaining, resetAt, unlimited }
$payKit->can($customerId, 'pro_models');               // bool
$report = $payKit->report($customerId, 'messages', 3); // ReportResult { success, balance }
```

`report()` either deducts the whole amount or nothing. Balances reset lazily on the next `check`/`report` after `resetAt`. The next reset stays aligned on the period boundary.

### Controller attribute, voter, Twig

```php
use PayKit\Bundle\Security\RequiresFeature;

#[RequiresFeature('pro_models')]                 // 402 if not in plan, 401 if no customer
public function export(): Response { /* ... */ }

#[RequiresFeature('messages', consume: 1)]       // checks, then reports 1 unit after a 2xx response
public function chat(): Response { /* ... */ }

// with symfony/security-bundle:
$this->denyAccessUnlessGranted('PAYKIT_FEATURE', 'pro_models');
```

```twig
{% if paykit_can('pro_models') %}<a href="{{ path('export') }}">Export</a>{% endif %}
{{ paykit_balance('messages').remaining }} messages left
{% if paykit_plan('base') == 'free' %}<a href="{{ path('pricing') }}">Upgrade</a>{% endif %}
```

### Customer resolution

By default the logged-in user's identifier is the customer id. Implement `PayKit\Bundle\Security\BillableInterface` on your user to bill an organization instead, or provide your own `CustomerResolverInterface` service (`paykit.customer_resolver`).

### HTTP API (for JS frontends)

| Route | Body | Returns |
|---|---|---|
| `POST /paykit/api/subscribe` | `{ planId, successUrl?, cancelUrl?, forceCheckout? }` | `{ action, paymentUrl, invoice, requiredAction }` |
| `POST /paykit/api/customer-portal` | `{ returnUrl }` | `{ url }` |
| `GET  /paykit/api/customer` | | customer, subscriptions, entitlements |
| `POST /paykit/webhook` | Stripe payload | `{ received, processed, duplicates }` |

The customer always comes from the resolver. A `customerId` in the body that does not match it gets a 403. Cross-origin POSTs are rejected unless the origin is listed in `trusted_origins`, and relative return URLs are resolved against the request origin.

### Events

| Event | When |
|---|---|
| `CustomerUpdatedEvent` | after any subscription/entitlement change (PayKit's `customer.updated`) |
| `SubscriptionActivatedEvent` | a plan became active (`->previous` holds the replaced one) |
| `SubscriptionScheduledEvent` | a downgrade/cancellation was scheduled (`targetPlanId`) or cleared (`null`) |
| `SubscriptionEndedEvent` | a subscription ended |
| `WebhookProcessedEvent` | a provider event was applied (never fired for duplicates) |
| `UsageReportedEvent` | after `report()` |

```php
#[AsEventListener]
public function onCustomerUpdated(CustomerUpdatedEvent $event): void
{
    $this->cache->invalidateTags(['billing_'.$event->customerId]);
}
```

### Testing your app

Set `paykit.provider: fake` in the test environment. `PayKit\Bundle\Testing\FakeProvider` records calls, creates fake checkout URLs, and lets you push webhook events (`queueWebhook()` + header `X-Fake-Signature: valid`). Swap the `clock` service for a `MockClock` to test period resets.

### Custom providers

Implement `PayKit\Bundle\Provider\PaymentProviderInterface` and alias it:

```yaml
services:
    PayKit\Bundle\Provider\PaymentProviderInterface: '@App\Billing\PaddleProvider'
```

## PayKit (TypeScript) → Symfony mapping

| PayKit TS | This bundle |
|---|---|
| `feature({ id, type })` | `paykit.features.<id>: boolean\|metered` |
| `plan({ id, group, default, price, includes })` | `paykit.plans.<id>: { group, default, price, includes }` |
| `messages({ limit, reset })` | `includes: { messages: { limit, reset } }` |
| `createPayKit({ stripe, database, products })` | bundle config + Doctrine connection |
| `paykit.subscribe({...})` | `PayKit::subscribe()` → `SubscribeResult` |
| `paykit.check()` / `paykit.report()` | `PayKit::check()` / `can()` / `report()` |
| `paykit.upsertCustomer/getCustomer/listCustomers/deleteCustomer` | same names on `PayKit` |
| `paykit.customerPortal()` | `PayKit::customerPortal()` |
| `paykit.handleWebhook()` / `/paykit/webhook` | `PayKit::handleWebhook()` / `WebhookController` |
| `identify(request)` | `CustomerResolverInterface` / `BillableInterface` |
| `on: { "customer.updated" }` | `CustomerUpdatedEvent` (+ finer-grained events) |
| `createPayKitClient()` | JSON routes under `/paykit/api` |
| `trustedOrigins`, `basePath` | `paykit.trusted_origins`, route import `prefix` |
| `paykitjs push` / `status` | `bin/console paykit:push` / `paykit:status` |
| Drizzle migrations | Doctrine migration `PayKit\Bundle\Migrations\Version20261016000000` |
| `PaymentProvider` (Stripe) | `PaymentProviderInterface` + `StripeProvider` |
| test clocks | `FakeProvider` + Symfony `MockClock` |
| — | `#[RequiresFeature]`, `PAYKIT_FEATURE` voter, Twig `paykit_can/balance/plan` |

## Architecture

```
src/
├── Catalog/      plan & feature definitions (pure value objects, config → Catalog)
├── Entity/       Doctrine entities (paykit_* tables)
├── Repository/   BillingRepository — every query in one adapter
├── Service/      SubscriptionService, EntitlementService, CustomerService, ProductSyncService, WebhookProcessor
├── Provider/     PaymentProviderInterface (port) + DTOs, Stripe/StripeProvider (adapter)
├── Security/     RequiresFeature attribute + listener, FeatureVoter, customer resolvers
├── Twig/         PayKitExtension
├── Controller/   WebhookController, ApiController
├── Command/      paykit:push, paykit:status
├── Event/        Symfony events
├── Testing/      FakeProvider
└── PayKit.php    façade
migrations/       portable Doctrine migration
config/routes.php
recipe/           Symfony Flex recipe (for recipes-contrib)
```

See [docs/paykit-analysis.md](docs/paykit-analysis.md) for the study of the original project.

## Not yet supported (compared to PayKit)

- The PayKit dashboard (`@paykitjs/dash`) and `paykitjs listen` (use `stripe listen`).
- Stripe test-clock helpers, multi-currency price sets, and quantities/seats billing.
- Typed JS client generation.

## License

MIT, see [LICENSE](LICENSE). PayKit is also MIT-licensed. This bundle contains no PayKit source code.
