---
title: Fournisseurs personnalisés
description: Remplacez Stripe par un autre fournisseur de paiement.
sidebar:
  order: 9
---

Le bundle parle aux paiements par un seul port, `PaymentProviderInterface`.
Stripe est l'adaptateur livré avec lui. Pour utiliser un autre fournisseur,
implémentez l'interface et déclarez l'alias :

```yaml
services:
    PayKit\Bundle\Provider\PaymentProviderInterface: '@App\Billing\PaddleProvider'
```

L'interface couvre :

| Domaine | Méthodes |
|---|---|
| Clients | `createCustomer`, `updateCustomer`, `deleteCustomer` |
| Catalogue | `syncProduct` |
| Checkout | `createCheckout` |
| Abonnements | `createSubscription`, `updateSubscription`, `scheduleSubscriptionChange`, `cancelSubscription`, `resumeSubscription` |
| Portail | `createPortalSession` |
| Webhooks | `parseWebhook` |

Les données traversent le port sous forme de DTO de `PayKit\Bundle\Provider\Dto`
(`CheckoutRequest`, `ProviderSubscription`, `ProviderEvent`...).
`StripeProvider` et `FakeProvider` sont deux implémentations complètes à lire
avant d'écrire la vôtre.
