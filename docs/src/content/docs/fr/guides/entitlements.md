---
title: Droits et mesure d'usage
description: Vérifiez l'accès, consommez l'usage de façon atomique et laissez les soldes se réinitialiser seuls.
sidebar:
  order: 3
---

## Vérifier l'accès

```php
$check = $payKit->check($customerId, 'messages');   // CheckResult { allowed, balance }
$check->balance?->remaining;                        // Balance { limit, remaining, resetAt, unlimited }

$payKit->can($customerId, 'pro_models');            // bool
```

Les deux acceptent un troisième argument, `required` (par défaut `1`), pour demander
si le client peut assumer plus d'une unité :

```php
if (!$payKit->can($customerId, 'messages', required: 10)) {
    // not enough messages left for a batch of 10
}
```

Pour une fonctionnalité booléenne, `balance` vaut `null`.

## Déclarer l'usage

```php
$report = $payKit->report($customerId, 'messages', 3); // ReportResult { success, balance }
```

`report()` **déduit le montant entier ou rien**. Quand le solde est trop bas,
`success` vaut `false` et aucune unité n'est consommée. Les déclarations
concurrentes sont sûres : la déduction est atomique dans la base de données.

Un schéma courant vérifie d'abord, fait le travail, puis déclare :

```php
if (!$payKit->check($userId, 'messages')->allowed) {
    throw new HttpException(402, 'Usage limit reached');
}
$answer = $chat->reply($prompt);
$payKit->report($userId, 'messages');
```

L'attribut [`#[RequiresFeature]`](/fr/guides/access-control/) fait la même chose
pour toute une action de contrôleur.

## Réinitialisation des périodes

Il n'y a pas de cron. Un solde se réinitialise **à la demande**, au premier
`check()` ou `report()` après son `resetAt`. Le `resetAt` suivant reste aligné
sur la borne de période, donc un solde mensuel se réinitialise toujours le même
jour du mois, quel que soit le retard de la réinitialisation.

## L'objet Balance

| Propriété | Type | Signification |
|---|---|---|
| `limit` | `int` | unités accordées par période, sommées entre les offres |
| `remaining` | `int` | unités restantes dans la période courante |
| `resetAt` | `?DateTimeImmutable` | prochaine réinitialisation, `null` quand le droit n'a pas de reset |
| `unlimited` | `bool` | au moins une offre active accorde la fonctionnalité sans limite |

`$balance->used()` renvoie `limit - remaining`, ou `0` quand c'est illimité. Un
solde illimité rapporte `limit` et `remaining` à `0` : testez `unlimited` d'abord.
