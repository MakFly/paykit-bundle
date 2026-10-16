---
title: Webhooks
description: Comment le bundle reçoit les événements Stripe, et pourquoi chacun n'est appliqué qu'une seule fois.
sidebar:
  order: 5
---

Stripe dit à votre application ce qui s'est passé (un Checkout terminé, un
renouvellement payé, un schedule qui a changé d'offre) par des webhooks. Le
bundle expose un endpoint pour eux :

```
POST /paykit/webhook
```

Le chemin suit le `prefix` de l'import de routes.

## Configuration Stripe

Créez un endpoint pointant vers `https://your-app.example/paykit/webhook` et
abonnez-le à :

- `checkout.session.completed`
- `customer.subscription.*`
- `invoice.*`
- `payment_method.attached` et `payment_method.detached`

Copiez son secret de signature dans `STRIPE_WEBHOOK_SECRET`. Les requêtes dont
la signature est invalide sont rejetées.

Pour le développement local :

```bash
stripe listen --forward-to localhost:8000/paykit/webhook
```

`stripe listen` affiche un secret de signature temporaire : utilisez-le comme
`STRIPE_WEBHOOK_SECRET` tant qu'il tourne.

## Idempotence

Stripe réessaie les livraisons et peut envoyer le même événement deux fois.
Chaque événement est enregistré avant d'être appliqué :

- un événement est **réclamé une fois** ; une seconde livraison est comptée comme doublon et ignorée
- un échec est enregistré, et l'événement est appliqué de nouveau au prochain essai de Stripe
- `WebhookProcessedEvent` n'est distribué que pour les événements réellement appliqués

L'endpoint répond `{ received, processed, duplicates }`.

## Traiter les webhooks vous-même

Pour recevoir les webhooks depuis votre propre contrôleur, appelez la façade :

```php
$summary = $payKit->handleWebhook($request->getContent(), $request->headers->all());
```
