---
title: API PHP
description: La façade PayKit et les objets de valeur qu'elle renvoie.
sidebar:
  order: 2
---

Injectez `PayKit\Bundle\PayKit` (alias de service `paykit`).

## Méthodes

| Méthode | Renvoie |
|---|---|
| `subscribe(string $customerId, string $planId, ?string $successUrl = null, ?string $cancelUrl = null, bool $forceCheckout = false)` | `SubscribeResult` |
| `check(string $customerId, string $featureId, int $required = 1)` | `CheckResult` |
| `can(string $customerId, string $featureId, int $required = 1)` | `bool` |
| `report(string $customerId, string $featureId, int $amount = 1)` | `ReportResult` |
| `upsertCustomer(string $id, ?string $email = null, ?string $name = null, ?array $metadata = null)` | entité `Customer` |
| `getCustomer(string $id)` | `?CustomerView` |
| `listCustomers(int $limit = 50, int $offset = 0, array $planIds = [])` | `array` |
| `deleteCustomer(string $id)` | `void` |
| `customerPortal(string $customerId, string $returnUrl)` | `string` (URL) |
| `handleWebhook(string $payload, array $headers)` | `array` (`received`, `processed`, `duplicates`) |
| `push(bool $dryRun = false)` | `array` de résultats de synchronisation |
| `catalog()` | `Catalog` |

## Objets de valeur

Tous vivent dans `PayKit\Bundle\Model`.

### `SubscribeResult`

| Propriété | Type |
|---|---|
| `action` | `SubscribeAction` |
| `paymentUrl` | `?string` |
| `invoice` | `?ProviderInvoice` |
| `requiredActionUrl` | `?string` |

`SubscribeAction` est une énumération : `Checkout`, `Activated`, `Upgraded`, `Scheduled`,
`Resumed`, `Noop`. Voir [Abonnements](/fr/guides/subscriptions/#ce-que-fait-chaque-changement).

### `CheckResult` et `ReportResult`

| Classe | Propriétés |
|---|---|
| `CheckResult` | `bool $allowed`, `?Balance $balance` |
| `ReportResult` | `bool $success`, `?Balance $balance` |

### `Balance`

| Propriété | Type |
|---|---|
| `limit` | `int` |
| `remaining` | `int` |
| `resetAt` | `?DateTimeImmutable` |
| `unlimited` | `bool` |

Méthodes : `used(): int`, `toArray(): array`.

### `CustomerView`

| Propriété | Type |
|---|---|
| `customer` | entité `Customer` |
| `subscriptions` | liste d'entités `Subscription` |
| `entitlements` | `array<string, Balance>`, indexé par identifiant de fonctionnalité |

Méthodes : `planIds(): array`, `toArray(): array`.

## Exceptions

`PayKit\Bundle\Exception\PayKitException` est levée pour une offre ou une
fonctionnalité inconnue, et pour les autres mauvais usages. `InvalidWebhookException`
est levée pour un webhook dont la signature ne se vérifie pas.
