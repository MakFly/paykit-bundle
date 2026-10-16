---
title: Installation
description: Installez le bundle, créez ses tables et connectez le webhook Stripe.
sidebar:
  order: 1
---

## Prérequis

- PHP 8.2 ou plus récent
- Symfony 7.1 ou plus récent
- Doctrine ORM 3 et une base prise en charge par Doctrine DBAL (PostgreSQL, MySQL ou MariaDB, SQLite)

Des paquets optionnels débloquent d'autres intégrations :

| Paquet | Apporte |
|---|---|
| `symfony/security-bundle` | le voter `PAYKIT_FEATURE` et le résolveur de client par défaut |
| `twig/twig` | les fonctions Twig `paykit_*` |
| `doctrine/doctrine-migrations-bundle` | la migration de base de données fournie |

## Installer le paquet

```bash
composer require makfly/paykit-bundle
```

Avec Symfony Flex, la recette enregistre le bundle, copie une configuration de départ
et ajoute `STRIPE_SECRET_KEY` et `STRIPE_WEBHOOK_SECRET` à votre `.env`.

Sans Flex, enregistrez le bundle et importez les routes vous-même :

```php
// config/bundles.php
return [
    // ...
    PayKit\Bundle\PayKitBundle::class => ['all' => true],
];
```

```yaml
# config/routes/paykit.yaml
paykit:
    resource: '@PayKitBundle/config/routes.php'
    prefix: /paykit
```

Le `prefix` décide où vivent le webhook et l'API JSON. Le reste de cette
documentation suppose `/paykit`.

## Créer les tables et pousser les offres

```bash
php bin/console doctrine:migrations:migrate   # the bundle registers its own migration
php bin/console paykit:push                   # creates Stripe products and prices, and DB versions
```

Relancez `paykit:push` à chaque modification des offres, et dans votre pipeline de
déploiement. Voir [Commandes de console](/fr/reference/console/).

## Connecter le webhook Stripe

Pointez un endpoint de webhook Stripe vers `https://your-app.example/paykit/webhook`
et abonnez-le à ces événements :

- `checkout.session.completed`
- `customer.subscription.*`
- `invoice.*`
- `payment_method.attached` et `payment_method.detached`

En développement local, transférez les événements avec la CLI Stripe :

```bash
stripe listen --forward-to localhost:8000/paykit/webhook
```

Ensuite, [déclarez vos fonctionnalités et vos offres](/fr/getting-started/configuration/).
