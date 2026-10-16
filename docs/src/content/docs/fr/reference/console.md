---
title: Commandes de console
description: Synchronisez les offres vers le fournisseur et vérifiez l'installation en CI.
sidebar:
  order: 3
---

## `paykit:push`

Synchronise les fonctionnalités et les offres configurées vers la base de données
et le fournisseur de paiement.

```bash
php bin/console paykit:push
php bin/console paykit:push --dry-run
```

| Option | Effet |
|---|---|
| `--dry-run` | montre ce qui changerait, sans rien écrire |

Un changement de prix ou de fonctionnalité crée une nouvelle version de produit.
Un renommage met à jour la version courante. Les abonnés existants gardent leur
version. Lancez la commande après chaque changement d'offre, et dans votre
pipeline de déploiement.

## `paykit:status`

Valide l'installation : le schéma de base de données et l'état de synchronisation
des offres.

```bash
php bin/console paykit:status
php bin/console paykit:status --throw
```

| Option | Effet |
|---|---|
| `--throw` | quitte avec le code 1 quand quelque chose demande attention, pour la CI |

Une étape de déploiement typique :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console paykit:push
php bin/console paykit:status --throw
```
