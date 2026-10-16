---
title: API HTTP
description: Routes JSON pour un frontend JavaScript, et les règles qui les gardent sûres.
sidebar:
  order: 6
---

Une application monopage peut piloter la facturation par des routes JSON sous le préfixe de routes :

| Route | Corps | Renvoie |
|---|---|---|
| `POST /paykit/api/subscribe` | `{ planId, successUrl?, cancelUrl?, forceCheckout? }` | `{ action, paymentUrl, invoice, requiredAction }` |
| `POST /paykit/api/customer-portal` | `{ returnUrl }` | `{ url }` |
| `GET /paykit/api/customer` | | client, abonnements, droits |
| `POST /paykit/webhook` | charge utile Stripe | `{ received, processed, duplicates }` |

```ts
const response = await fetch('/paykit/api/subscribe', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ planId: 'pro', successUrl: '/billing?upgraded=1' }),
});
const result = await response.json();
if (result.paymentUrl) window.location.assign(result.paymentUrl);
```

## Règles de sécurité

- **Le client vient toujours du [résolveur](/fr/guides/access-control/#résolution-du-client).**
  Un `customerId` dans le corps qui ne correspond pas reçoit un 403.
- **Les POST cross-origin sont rejetés** sauf si l'origine est listée dans
  `paykit.trusted_origins`. Ajoutez l'origine de votre frontend seulement quand
  il est servi depuis un autre domaine que l'API.
- **Les URL de retour relatives** sont résolues par rapport à l'origine de la requête.

```yaml
paykit:
    trusted_origins: ['https://app.example.com']
```
