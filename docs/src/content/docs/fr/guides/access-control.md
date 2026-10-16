---
title: Contrôle d'accès
description: Protégez les contrôleurs avec un attribut, les templates avec Twig, et choisissez qui est le client.
sidebar:
  order: 4
---

## L'attribut `#[RequiresFeature]`

```php
use PayKit\Bundle\Security\RequiresFeature;

#[RequiresFeature('pro_models')]                 // 402 if not in plan, 401 if no customer
public function export(): Response { /* ... */ }

#[RequiresFeature('messages', consume: 1)]       // checks, then reports 1 unit after a 2xx response
public function chat(): Response { /* ... */ }
```

| Argument | Défaut | Signification |
|---|---|---|
| `feature` | | l'identifiant de la fonctionnalité |
| `required` | `1` | unités qu'il doit rester au client |
| `consume` | `0` | unités déclarées après une réponse réussie (2xx) |
| `statusCode` | `402` | statut renvoyé quand l'accès est refusé |
| `message` | une phrase générique | corps du refus |

Avec `consume`, l'usage n'est déclaré que si l'action réussit. Une action en
échec ne coûte rien au client.

## Le voter `PAYKIT_FEATURE`

Avec `symfony/security-bundle` installé :

```php
$this->denyAccessUnlessGranted('PAYKIT_FEATURE', 'pro_models');
```

```twig
{% if is_granted('PAYKIT_FEATURE', 'pro_models') %}...{% endif %}
```

## Fonctions Twig

Avec `twig/twig` installé :

```twig
{% if paykit_can('pro_models') %}<a href="{{ path('export') }}">Export</a>{% endif %}
{{ paykit_balance('messages').remaining }} messages left
{% if paykit_plan('base') == 'free' %}<a href="{{ path('pricing') }}">Upgrade</a>{% endif %}
```

| Fonction | Renvoie |
|---|---|
| `paykit_can(feature)` | si le client courant a la fonctionnalité ; `false` sans client |
| `paykit_balance(feature)` | le `Balance` d'une fonctionnalité mesurée ; `null` pour une fonctionnalité booléenne ou sans client |
| `paykit_plan(group = 'default')` | l'identifiant de l'offre active du client dans ce groupe, ou `null` |

Chaque fonction prend un dernier argument optionnel, un identifiant de client,
pour lire un autre client que le courant (dans une page d'administration, par exemple).

## Résolution du client

L'attribut, le voter, Twig et l'[API HTTP](/fr/guides/http-api/) demandent tous
à un **résolveur de client** qui est le client courant. C'est la frontière de
confiance du bundle.

Par défaut, l'identifiant de l'utilisateur connecté est l'identifiant du client.

### Facturer une organisation plutôt qu'un utilisateur

Implémentez `BillableInterface` sur votre utilisateur. Le résolveur par défaut
l'utilise quand elle est présente :

```php
use PayKit\Bundle\Security\BillableInterface;

class User implements UserInterface, BillableInterface
{
    public function getBillingCustomerId(): string { return 'org_'.$this->organization->getId(); }
    public function getBillingEmail(): ?string { return $this->organization->getBillingEmail(); }
    public function getBillingName(): ?string { return $this->organization->getName(); }
}
```

### Écrire votre propre résolveur

Implémentez `CustomerResolverInterface` et pointez `paykit.customer_resolver` vers le service :

```php
use PayKit\Bundle\Security\CustomerResolverInterface;
use PayKit\Bundle\Security\ResolvedCustomer;

final class TenantCustomerResolver implements CustomerResolverInterface
{
    public function __construct(private TenantContext $tenants) {}

    public function resolve(?Request $request = null): ?ResolvedCustomer
    {
        $tenant = $this->tenants->current();

        return $tenant ? new ResolvedCustomer($tenant->id, $tenant->email, $tenant->name) : null;
    }
}
```

```yaml
paykit:
    customer_resolver: App\Billing\TenantCustomerResolver
```

Renvoyer `null` signifie « pas de client » : l'attribut répond 401.
