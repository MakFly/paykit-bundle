---
title: Abonnements
description: Un seul appel à subscribe() gère les nouvelles offres, les montées en gamme, les descentes, les résiliations et les reprises.
sidebar:
  order: 2
---

`subscribe()` prend le client, l'offre cible et l'adresse où renvoyer le client
après un Checkout Stripe. Il décide ce que signifie le changement et renvoie un
`SubscribeResult` dont `action` vous dit ce qui s'est passé.

```php
use PayKit\Bundle\Model\SubscribeAction;

$result = $payKit->subscribe($customerId, 'pro', successUrl: $ok, cancelUrl: $back);

match ($result->action) {
    SubscribeAction::Checkout  => $this->redirect($result->paymentUrl ?? $result->requiredActionUrl),
    SubscribeAction::Activated, SubscribeAction::Upgraded => $this->addFlash('success', 'Welcome to Pro!'),
    SubscribeAction::Scheduled => $this->addFlash('info', 'Your plan changes at the end of the period.'),
    SubscribeAction::Resumed, SubscribeAction::Noop => null,
};
```

## Ce que fait chaque changement

| Changement | Action | Effet |
|---|---|---|
| gratuit vers payant, carte enregistrée | `Activated` | abonnement Stripe créé et débité |
| gratuit vers payant, pas de carte (ou `forceCheckout: true`) | `Checkout` | redirection vers `paymentUrl` ; l'offre s'active au webhook |
| pro vers ultra | `Upgraded` | immédiat et au prorata (`Checkout` sans carte) |
| ultra vers pro | `Scheduled` | bascule en fin de période via un subscription schedule Stripe |
| pro vers gratuit | `Scheduled` | `cancel_at_period_end`, puis retour à l'offre gratuite |
| pro avec un changement en attente, vers pro | `Resumed` | le changement en attente est annulé |
| même offre | `Noop` | rien |

Une descente conserve l'accès actuel du client jusqu'à la fin de la période
payée. Rappeler `subscribe()` avec l'offre courante annule le changement en attente.

## Checkout et webhooks

Quand l'action est `Checkout`, l'offre **n'est pas encore active**. Elle le devient
quand Stripe envoie `checkout.session.completed`. N'accordez pas l'accès depuis
l'URL de succès : lisez l'état du client, que le webhook met à jour. Voir
[Webhooks](/fr/guides/webhooks/).

## Clients

```php
$payKit->upsertCustomer($user->getId(), $user->getEmail(), $user->getName()); // puts new customers on default plans
$view = $payKit->getCustomer($user->getId());   // ->subscriptions, ->entitlements['messages']->remaining
$page = $payKit->listCustomers(limit: 50, offset: 0, planIds: ['pro']);
$payKit->deleteCustomer($id);                   // cancels Stripe subscriptions immediately
$url  = $payKit->customerPortal($id, returnUrl: 'https://app.example.com/billing');
```

`customerPortal()` renvoie une URL du portail de facturation Stripe, où le client
gère ses cartes et ses factures.
