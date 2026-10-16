---
title: Console commands
description: Sync plans to the provider and check the setup in CI.
sidebar:
  order: 3
---

## `paykit:push`

Syncs configured features and plans to the database and the payment provider.

```bash
php bin/console paykit:push
php bin/console paykit:push --dry-run
```

| Option | Effect |
|---|---|
| `--dry-run` | shows what would change without writing anything |

A price or feature change creates a new product version; a rename updates the current
one. Existing subscribers keep their version. Run it after each plan change and in
your deploy pipeline.

## `paykit:status`

Validates the setup: the database schema and the plan sync state.

```bash
php bin/console paykit:status
php bin/console paykit:status --throw
```

| Option | Effect |
|---|---|
| `--throw` | exits with code 1 when something needs attention, for CI |

A typical deploy step:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console paykit:push
php bin/console paykit:status --throw
```
