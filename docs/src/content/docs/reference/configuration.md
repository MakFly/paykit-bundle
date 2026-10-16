---
title: Configuration
description: Every option of config/packages/paykit.yaml, with its default.
sidebar:
  order: 1
---

```yaml
# config/packages/paykit.yaml
paykit:
    # "stripe" or "fake" (in memory, for tests and offline development)
    provider: stripe

    # ISO currency of every price
    currency: usd

    stripe:
        secret_key: '%env(default::STRIPE_SECRET_KEY)%'
        webhook_secret: '%env(default::STRIPE_WEBHOOK_SECRET)%'

    # Origins allowed to POST to the JSON API from another domain
    trusted_origins: []

    # Service id implementing CustomerResolverInterface
    # (default: the security user's identifier)
    customer_resolver: null

    features:
        <feature_id>: boolean | metered

    plans:
        <plan_id>:
            name: null          # defaults to the id, title-cased
            group: default
            default: false      # at most one default plan per group
            trial_days: null    # integer >= 1
            price:              # omit for a free plan
                amount: ~       # required, major units, >= 0.01
                interval: ~     # required: month | year
            includes:
                <feature_id>:   # ~ for a boolean feature or an unlimited metered one
                    limit: null # integer
                    reset: null # day | week | month | year
```

## Options

| Option | Type | Default | Notes |
|---|---|---|---|
| `provider` | `stripe` \| `fake` | `stripe` | for another provider, alias `PaymentProviderInterface` ([guide](/guides/custom-providers/)) |
| `currency` | string | `usd` | lowercased when sent to the provider |
| `stripe.secret_key` | string | `%env(default::STRIPE_SECRET_KEY)%` | |
| `stripe.webhook_secret` | string | `%env(default::STRIPE_WEBHOOK_SECRET)%` | |
| `trusted_origins` | list of strings | `[]` | see [HTTP API](/guides/http-api/#security-rules) |
| `customer_resolver` | service id | `null` | see [Customer resolution](/guides/access-control/#customer-resolution) |
| `features.<id>` | `boolean` \| `metered` | | the short form stands for `{ type: ... }` |
| `plans.<id>.name` | string | id, title-cased | display name sent to the provider |
| `plans.<id>.group` | string | `default` | one active plan per group per customer |
| `plans.<id>.default` | bool | `false` | plan given to new customers |
| `plans.<id>.trial_days` | int | `null` | |
| `plans.<id>.price.amount` | float | | `19` means 19.00 in the currency |
| `plans.<id>.price.interval` | `month` \| `year` | | |
| `plans.<id>.includes.<feature>.limit` | int | `null` | `null` on a metered feature means unlimited |
| `plans.<id>.includes.<feature>.reset` | `day` \| `week` \| `month` \| `year` | `null` | |

Ids of features and plans are 1 to 64 characters of lowercase letters, digits, dashes
or underscores. The catalog rules are listed in
[Plans and features](/guides/plans/#validation).
