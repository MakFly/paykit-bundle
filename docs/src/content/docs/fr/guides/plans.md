---
title: Offres et fonctionnalités
description: Déclarez les fonctionnalités, groupez les offres, cumulez les extensions et versionnez les prix sans risque.
sidebar:
  order: 1
---

## Fonctionnalités

Une fonctionnalité est quelque chose que votre application contrôle. Elle a l'un de ces deux types :

| Type | Signification | Vérifiée avec |
|---|---|---|
| `boolean` | l'offre l'accorde ou non | `can()`, `check()` |
| `metered` | un nombre d'unités par période | `check()`, consommée avec `report()` |

```yaml
paykit:
    features:
        messages: metered
        pro_models: boolean
```

Les identifiants de fonctionnalité et d'offre font de 1 à 64 caractères : lettres
minuscules, chiffres, tirets ou underscores.

## Offres

Une offre accorde des fonctionnalités via `includes`. Une fonctionnalité booléenne
s'accorde avec `~`. Une fonctionnalité mesurée prend une `limit` et une période de
`reset` (`day`, `week`, `month` ou `year`). Une fonctionnalité mesurée sans limite
est **illimitée**.

```yaml
paykit:
    plans:
        free:
            group: base
            default: true
            includes:
                messages: { limit: 100, reset: month }
        pro:
            name: Pro                               # defaults to the id, title-cased
            group: base
            price: { amount: 19, interval: month }  # major units: 19 = $19.00
            trial_days: 14
            includes:
                messages: { limit: 2000, reset: month }
                pro_models: ~
        ultra:
            group: base
            price: { amount: 49, interval: month }
            includes:
                messages: ~                         # metered without limit = unlimited
                pro_models: ~
```

Une offre sans `price` est gratuite. Les prix sont dans la devise fixée par
`paykit.currency`, en unités majeures, avec un intervalle `month` ou `year`.

## Groupes et extensions

Un client détient **au plus une offre par groupe**. S'abonner à une autre offre du
même groupe change l'offre. Une offre d'un autre groupe s'ajoute à côté.

```yaml
paykit:
    plans:
        extra_messages:                 # add-on: different group, so it stacks
            group: addons
            price: { amount: 5, interval: month }
            includes:
                messages: { limit: 500, reset: month }
```

Un client en `pro` avec `extra_messages` reçoit 2 000 + 500 messages par mois :
**les droits se cumulent entre les offres**. Si une offre active accorde une
fonctionnalité mesurée sans limite, la fonctionnalité est illimitée.

Les offres sans `group` appartiennent au groupe `default`.

## Offres par défaut

`default: true` place les nouveaux clients sur cette offre lorsqu'ils sont créés
avec `upsertCustomer()`, et c'est là qu'un client arrive quand une offre payante
du groupe se termine. Chaque groupe a au plus une offre par défaut.

## Validation

Le catalogue est validé à la compilation du conteneur. Ces erreurs font échouer
la construction au lieu d'échouer en production :

- une offre qui inclut une fonctionnalité non déclarée
- une fonctionnalité booléenne accordée avec une limite, ou l'inverse
- deux offres par défaut dans le même groupe
- un identifiant invalide, un prix nul ou un intervalle inconnu

## Versionnage

`paykit:push` compare la configuration à ce qui est stocké :

- un **changement de prix ou de fonctionnalité** crée une nouvelle version de
  produit, avec un nouveau prix Stripe. Les abonnés existants gardent la version
  qu'ils ont achetée.
- un **renommage** met à jour la version courante sur place.

```bash
php bin/console paykit:push --dry-run   # preview
php bin/console paykit:push             # apply
```

Ajoutez `paykit:status --throw` à la CI pour faire échouer un déploiement quand
les offres stockées ne correspondent plus à la configuration. Voir
[Commandes de console](/fr/reference/console/).
