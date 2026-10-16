---
title: PHP API
description: The PayKit façade and the value objects it returns.
sidebar:
  order: 2
---

Inject `PayKit\Bundle\PayKit` (service alias `paykit`).

## Methods

| Method | Returns |
|---|---|
| `subscribe(string $customerId, string $planId, ?string $successUrl = null, ?string $cancelUrl = null, bool $forceCheckout = false)` | `SubscribeResult` |
| `check(string $customerId, string $featureId, int $required = 1)` | `CheckResult` |
| `can(string $customerId, string $featureId, int $required = 1)` | `bool` |
| `report(string $customerId, string $featureId, int $amount = 1)` | `ReportResult` |
| `upsertCustomer(string $id, ?string $email = null, ?string $name = null, ?array $metadata = null)` | `Customer` entity |
| `getCustomer(string $id)` | `?CustomerView` |
| `listCustomers(int $limit = 50, int $offset = 0, array $planIds = [])` | `array` |
| `deleteCustomer(string $id)` | `void` |
| `customerPortal(string $customerId, string $returnUrl)` | `string` (URL) |
| `handleWebhook(string $payload, array $headers)` | `array` (`received`, `processed`, `duplicates`) |
| `push(bool $dryRun = false)` | `array` of sync results |
| `catalog()` | `Catalog` |

## Value objects

All live in `PayKit\Bundle\Model`.

### `SubscribeResult`

| Property | Type |
|---|---|
| `action` | `SubscribeAction` |
| `paymentUrl` | `?string` |
| `invoice` | `?ProviderInvoice` |
| `requiredActionUrl` | `?string` |

`SubscribeAction` is an enum: `Checkout`, `Activated`, `Upgraded`, `Scheduled`,
`Resumed`, `Noop`. See [Subscriptions](/guides/subscriptions/#what-each-change-does).

### `CheckResult` and `ReportResult`

| Class | Properties |
|---|---|
| `CheckResult` | `bool $allowed`, `?Balance $balance` |
| `ReportResult` | `bool $success`, `?Balance $balance` |

### `Balance`

| Property | Type |
|---|---|
| `limit` | `int` |
| `remaining` | `int` |
| `resetAt` | `?DateTimeImmutable` |
| `unlimited` | `bool` |

Methods: `used(): int`, `toArray(): array`.

### `CustomerView`

| Property | Type |
|---|---|
| `customer` | `Customer` entity |
| `subscriptions` | list of `Subscription` entities |
| `entitlements` | `array<string, Balance>`, keyed by feature id |

Methods: `planIds(): array`, `toArray(): array`.

## Exceptions

`PayKit\Bundle\Exception\PayKitException` is thrown for unknown plans or features and
other misuse. `InvalidWebhookException` is thrown for a webhook whose signature does
not verify.
