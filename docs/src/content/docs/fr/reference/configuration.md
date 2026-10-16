---
title: Configuration
description: Chaque option de config/packages/paykit.yaml, avec sa valeur par défaut.
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

| Option | Type | Défaut | Notes |
|---|---|---|---|
| `provider` | `stripe` \| `fake` | `stripe` | pour un autre fournisseur, aliasez `PaymentProviderInterface` ([guide](/fr/guides/custom-providers/)) |
| `currency` | `string` | `usd` | mise en minuscules à l'envoi au fournisseur |
| `stripe.secret_key` | `string` | `%env(default::STRIPE_SECRET_KEY)%` | |
| `stripe.webhook_secret` | `string` | `%env(default::STRIPE_WEBHOOK_SECRET)%` | |
| `trusted_origins` | liste de chaînes | `[]` | voir [API HTTP](/fr/guides/http-api/#règles-de-sécurité) |
| `customer_resolver` | identifiant de service | `null` | voir [Résolution du client](/fr/guides/access-control/#résolution-du-client) |
| `features.<id>` | `boolean` \| `metered` | | la forme courte signifie `{ type: ... }` |
| `plans.<id>.name` | `string` | l'identifiant, une majuscule par mot | nom affiché envoyé au fournisseur |
| `plans.<id>.group` | `string` | `default` | une offre active par groupe et par client |
| `plans.<id>.default` | bool | `false` | offre donnée aux nouveaux clients |
| `plans.<id>.trial_days` | int | `null` | |
| `plans.<id>.price.amount` | float | | `19` signifie 19.00 dans la devise |
| `plans.<id>.price.interval` | `month` \| `year` | | |
| `plans.<id>.includes.<feature>.limit` | int | `null` | `null` sur une fonctionnalité mesurée signifie illimité |
| `plans.<id>.includes.<feature>.reset` | `day` \| `week` \| `month` \| `year` | `null` | |

Les identifiants de fonctionnalités et d'offres font de 1 à 64 caractères :
lettres minuscules, chiffres, tirets ou underscores. Les règles du catalogue
sont listées dans [Offres et fonctionnalités](/fr/guides/plans/#validation).
