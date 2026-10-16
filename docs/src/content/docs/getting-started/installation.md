---
title: Installation
description: Install the bundle, create its tables and connect the Stripe webhook.
sidebar:
  order: 1
---

## Requirements

- PHP 8.2 or later
- Symfony 7.1 or later
- Doctrine ORM 3 and a database supported by Doctrine DBAL (PostgreSQL, MySQL or MariaDB, SQLite)

Optional packages unlock more integrations:

| Package | Unlocks |
|---|---|
| `symfony/security-bundle` | the `PAYKIT_FEATURE` voter and the default customer resolver |
| `twig/twig` | the `paykit_*` Twig functions |
| `doctrine/doctrine-migrations-bundle` | the bundled database migration |

## Install the package

```bash
composer require makfly/paykit-bundle
```

With Symfony Flex, the recipe registers the bundle, copies a starter configuration and
adds `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` to your `.env`.

Without Flex, register the bundle and import the routes yourself:

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

The `prefix` decides where the webhook and the JSON API live. The rest of this
documentation assumes `/paykit`.

## Create the tables and push the plans

```bash
php bin/console doctrine:migrations:migrate   # the bundle registers its own migration
php bin/console paykit:push                   # creates Stripe products and prices, and DB versions
```

Run `paykit:push` again each time you change plans, and in your deploy pipeline.
See [Console commands](/reference/console/).

## Connect the Stripe webhook

Point a Stripe webhook endpoint at `https://your-app.example/paykit/webhook` and
subscribe it to these events:

- `checkout.session.completed`
- `customer.subscription.*`
- `invoice.*`
- `payment_method.attached` and `payment_method.detached`

In local development, forward events with the Stripe CLI:

```bash
stripe listen --forward-to localhost:8000/paykit/webhook
```

Next, [declare your features and plans](/getting-started/configuration/).
