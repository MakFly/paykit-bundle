<h1 align="center">PayKit Bundle</h1>

<p align="center">
  Stripe billing for Symfony without the glue code.<br>
  Plans in YAML, feature gating, usage metering and idempotent webhooks, with billing state in your own database.
</p>

<p align="center">
  <a href="https://github.com/MakFly/paykit-bundle/actions/workflows/ci.yml"><img src="https://github.com/MakFly/paykit-bundle/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://packagist.org/packages/makfly/paykit-bundle"><img src="https://img.shields.io/packagist/v/makfly/paykit-bundle" alt="Latest version"></a>
  <a href="https://packagist.org/packages/makfly/paykit-bundle"><img src="https://img.shields.io/packagist/dependency-v/makfly/paykit-bundle/php" alt="PHP version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/MakFly/paykit-bundle" alt="License: MIT"></a>
</p>

---

```php
$result = $payKit->subscribe($user->getId(), 'pro', successUrl: $successUrl);
if ($result->paymentUrl) {
    return $this->redirect($result->paymentUrl);   // Stripe Checkout
}

if (!$payKit->check($userId, 'messages')->allowed) {
    throw new HttpException(402, 'Usage limit reached');
}
$answer = $chat->reply($prompt);
$payKit->report($userId, 'messages');              // consume 1 unit
```

> [!WARNING]
> Version 0.1 is experimental. The API may change before 1.0.

## Table of contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Documentation](#documentation)
- [Try it locally](#try-it-locally)
- [Credits](#credits)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Plans in config**: boolean and metered features, plans, groups, default free plans, prices and trials, validated when the container compiles.
- **One `subscribe()` for the whole lifecycle**: new subscriptions, prorated upgrades, downgrades scheduled at period end, cancel-to-free, resume and no-ops.
- **Entitlements**: `check()` and `can()`, a `#[RequiresFeature]` controller attribute, the `PAYKIT_FEATURE` voter and Twig functions.
- **Usage metering**: atomic `report()`, grants that stack across plans, unlimited grants and lazy period resets (no cron).
- **Stripe adapter**: Checkout, Billing Portal, subscription schedules and signature-verified webhooks.
- **Idempotent webhooks**: each event is claimed once, failures are retried, duplicates are skipped.
- **Versioned plans**: `paykit:push` creates a new product version when a price or a feature changes. Existing subscribers keep theirs.
- **Symfony events** for every change, and a **fake provider** for tests and offline development.

## Requirements

- PHP 8.2+
- Symfony 7.1+
- Doctrine ORM 3, with PostgreSQL, MySQL/MariaDB or SQLite

## Installation

```bash
composer require makfly/paykit-bundle
```

With Symfony Flex, the recipe registers the bundle and adds a starter configuration.
Without Flex, see [Installation](docs/src/content/docs/getting-started/installation.md).

Create the tables and push your plans:

```bash
php bin/console doctrine:migrations:migrate
php bin/console paykit:push
```

Then point a Stripe webhook at `https://your-app.example/paykit/webhook`
([events to subscribe](docs/src/content/docs/guides/webhooks.md)).

## Quick start

Declare what you sell:

```yaml
# config/packages/paykit.yaml
paykit:
    provider: stripe              # "fake" in tests and offline development
    currency: usd
    stripe:
        secret_key: '%env(STRIPE_SECRET_KEY)%'
        webhook_secret: '%env(STRIPE_WEBHOOK_SECRET)%'

    features:
        messages: metered
        pro_models: boolean

    plans:
        free:
            group: base
            default: true
            includes:
                messages: { limit: 100, reset: month }
        pro:
            group: base
            price: { amount: 19, interval: month }
            includes:
                messages: { limit: 2000, reset: month }
                pro_models: ~

when@test:
    paykit:
        provider: fake
```

Gate a controller:

```php
use PayKit\Bundle\Security\RequiresFeature;

#[RequiresFeature('messages', consume: 1)]   // 402 when the quota is spent, 1 unit reported on success
public function chat(): Response { /* ... */ }
```

Or a template:

```twig
{% if paykit_can('pro_models') %}<a href="{{ path('export') }}">Export</a>{% endif %}
{{ paykit_balance('messages').remaining }} messages left
```

## Documentation

The full documentation is an [Astro Starlight](https://starlight.astro.build) site in [`docs/`](docs).
Run it locally with `make docs`, or read the pages on GitHub:

| Topic | Page |
|---|---|
| Getting started | [Installation](docs/src/content/docs/getting-started/installation.md), [First configuration](docs/src/content/docs/getting-started/configuration.md) |
| Guides | [Plans and features](docs/src/content/docs/guides/plans.md), [Subscriptions](docs/src/content/docs/guides/subscriptions.md), [Entitlements and metering](docs/src/content/docs/guides/entitlements.md), [Access control](docs/src/content/docs/guides/access-control.md), [Webhooks](docs/src/content/docs/guides/webhooks.md), [HTTP API](docs/src/content/docs/guides/http-api.md), [Events](docs/src/content/docs/guides/events.md), [Testing](docs/src/content/docs/guides/testing.md), [Custom providers](docs/src/content/docs/guides/custom-providers.md) |
| Reference | [Configuration](docs/src/content/docs/reference/configuration.md), [PHP API](docs/src/content/docs/reference/php-api.md), [Console commands](docs/src/content/docs/reference/console.md), [Coming from PayKit (TypeScript)](docs/src/content/docs/reference/paykit-mapping.md), [Architecture](docs/src/content/docs/reference/architecture.md) |

## Try it locally

[PayKit Playground](examples/fullstack/README.md) is a Bun monorepo with React, Vite, shadcn/ui and a Symfony API linked to this bundle.
It ships two complete demo apps: Établi, an online store whose Club membership unlocks free shipping, member prices and express delivery credits, and Plume, a writing assistant SaaS where each rewrite uses a message from the quota and the Expert model needs a Pro plan.
It runs on SQLite with the fake provider, without Stripe credentials:

```bash
make install   # first time only
make dev       # React on http://127.0.0.1:5184, Symfony on http://127.0.0.1:8087
```

## Credits

This is an independent Symfony port of [PayKit](https://paykit.sh) ([getpaykit/paykit](https://github.com/getpaykit/paykit)), the billing framework for TypeScript by Max Katz and contributors.
It reuses PayKit's concepts and API shape, reimplemented in PHP, and contains no PayKit source code.
See [the original project study](docs/paykit-analysis.md).

## Contributing

Bug reports and pull requests are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) for the setup, the guidelines and the commit conventions.
Report security issues privately, not in a public issue.

## License

MIT, see [LICENSE](LICENSE). PayKit is also MIT-licensed.
