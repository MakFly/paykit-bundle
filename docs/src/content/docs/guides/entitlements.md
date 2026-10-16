---
title: Entitlements and metering
description: Check access, consume usage atomically and let balances reset on their own.
sidebar:
  order: 3
---

## Check access

```php
$check = $payKit->check($customerId, 'messages');   // CheckResult { allowed, balance }
$check->balance?->remaining;                        // Balance { limit, remaining, resetAt, unlimited }

$payKit->can($customerId, 'pro_models');            // bool
```

Both accept a third argument, `required` (default `1`), to ask whether the customer can
afford more than one unit:

```php
if (!$payKit->can($customerId, 'messages', required: 10)) {
    // not enough messages left for a batch of 10
}
```

For a boolean feature, `balance` is `null`.

## Report usage

```php
$report = $payKit->report($customerId, 'messages', 3); // ReportResult { success, balance }
```

`report()` **deducts the whole amount or nothing**. When the balance is too low,
`success` is `false` and no unit is consumed. Concurrent reports are safe: the
deduction is atomic in the database.

A common pattern checks first, does the work, then reports:

```php
if (!$payKit->check($userId, 'messages')->allowed) {
    throw new HttpException(402, 'Usage limit reached');
}
$answer = $chat->reply($prompt);
$payKit->report($userId, 'messages');
```

The [`#[RequiresFeature]` attribute](/guides/access-control/) does the same for a whole
controller action.

## Period resets

There is no cron. A balance resets **lazily**, on the first `check()` or `report()` after
its `resetAt`. The next `resetAt` stays aligned on the period boundary, so a monthly
balance always resets on the same day of the month, however late the reset is applied.

## The Balance object

| Property | Type | Meaning |
|---|---|---|
| `limit` | `int` | units granted per period, summed across plans |
| `remaining` | `int` | units left in the current period |
| `resetAt` | `?DateTimeImmutable` | next reset, `null` when the grant has no reset |
| `unlimited` | `bool` | at least one active plan grants the feature without a limit |

`$balance->used()` returns `limit - remaining`, or `0` when unlimited. An unlimited
balance reports `limit` and `remaining` as `0`: test `unlimited` first.
