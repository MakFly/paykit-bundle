---
title: Plans and features
description: Declare features, group plans, stack add-ons and version prices safely.
sidebar:
  order: 1
---

## Features

A feature is something your app gates. It has one of two types:

| Type | Meaning | Checked with |
|---|---|---|
| `boolean` | the plan grants it or not | `can()`, `check()` |
| `metered` | a number of units per period | `check()`, consumed with `report()` |

```yaml
paykit:
    features:
        messages: metered
        pro_models: boolean
```

Feature and plan ids are 1 to 64 characters of lowercase letters, digits, dashes or
underscores.

## Plans

A plan grants features through `includes`. A boolean feature is granted with `~`.
A metered feature takes a `limit` and a `reset` period (`day`, `week`, `month` or
`year`). A metered feature without a limit is **unlimited**.

```yaml
paykit:
    plans:
        free:
            group: base
            default: true
            includes:
                messages: { limit: 100, reset: month }
        pro:
            name: Pro                               # defaults to the id, title-cased
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
                messages: ~                         # metered without limit = unlimited
                pro_models: ~
```

A plan without `price` is free. Prices are in the currency set by `paykit.currency`,
in major units, with an interval of `month` or `year`.

## Groups and add-ons

A customer holds **at most one plan per group**. Subscribing to another plan of the
same group changes the plan. A plan in another group is added next to it.

```yaml
paykit:
    plans:
        extra_messages:                 # add-on: different group, so it stacks
            group: addons
            price: { amount: 5, interval: month }
            includes:
                messages: { limit: 500, reset: month }
```

A customer on `pro` with `extra_messages` gets 2,000 + 500 messages per month:
**grants stack across plans**. If any active plan grants a metered feature without a
limit, the feature is unlimited.

Plans without a `group` belong to the group `default`.

## Default plans

`default: true` puts new customers on that plan when they are created with
`upsertCustomer()`, and is where a customer lands when a paid plan of the group ends.
Each group has at most one default plan.

## Validation

The catalog is validated when the container compiles. These mistakes fail the build
instead of failing in production:

- a plan that includes an undeclared feature
- a boolean feature granted with a limit, or the reverse
- two default plans in the same group
- an invalid id, a price of zero or an unknown interval

## Versioning

`paykit:push` compares the configuration with what is stored:

- a **price or feature change** creates a new product version, with a new Stripe
  price. Existing subscribers keep the version they bought.
- a **rename** updates the current version in place.

```bash
php bin/console paykit:push --dry-run   # preview
php bin/console paykit:push             # apply
```

Add `paykit:status --throw` to CI to fail a deploy when the stored plans no longer match
the configuration. See [Console commands](/reference/console/).
