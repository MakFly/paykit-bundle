---
title: Access control
description: Gate controllers with an attribute, templates with Twig, and choose who the customer is.
sidebar:
  order: 4
---

## The `#[RequiresFeature]` attribute

```php
use PayKit\Bundle\Security\RequiresFeature;

#[RequiresFeature('pro_models')]                 // 402 if not in plan, 401 if no customer
public function export(): Response { /* ... */ }

#[RequiresFeature('messages', consume: 1)]       // checks, then reports 1 unit after a 2xx response
public function chat(): Response { /* ... */ }
```

| Argument | Default | Meaning |
|---|---|---|
| `feature` | | the feature id |
| `required` | `1` | units the customer must have left |
| `consume` | `0` | units reported after a successful (2xx) response |
| `statusCode` | `402` | status returned when access is denied |
| `message` | a generic sentence | body of the denial |

With `consume`, usage is reported only when the action succeeds. A failing action
costs the customer nothing.

## The `PAYKIT_FEATURE` voter

With `symfony/security-bundle` installed:

```php
$this->denyAccessUnlessGranted('PAYKIT_FEATURE', 'pro_models');
```

```twig
{% if is_granted('PAYKIT_FEATURE', 'pro_models') %}...{% endif %}
```

## Twig functions

With `twig/twig` installed:

```twig
{% if paykit_can('pro_models') %}<a href="{{ path('export') }}">Export</a>{% endif %}
{{ paykit_balance('messages').remaining }} messages left
{% if paykit_plan('base') == 'free' %}<a href="{{ path('pricing') }}">Upgrade</a>{% endif %}
```

| Function | Returns |
|---|---|
| `paykit_can(feature)` | whether the current customer has the feature; `false` without a customer |
| `paykit_balance(feature)` | the `Balance` of a metered feature; `null` for a boolean feature or without a customer |
| `paykit_plan(group = 'default')` | the id of the customer's active plan in that group, or `null` |

Each function takes an optional last argument, a customer id, to read another customer
than the current one (in an admin page, for instance).

## Customer resolution

The attribute, the voter, Twig and the [HTTP API](/guides/http-api/) all ask a
**customer resolver** who the current customer is. It is the trust boundary of the
bundle.

By default, the logged-in user's identifier is the customer id.

### Bill an organization instead of a user

Implement `BillableInterface` on your user. The default resolver uses it when it is
present:

```php
use PayKit\Bundle\Security\BillableInterface;

class User implements UserInterface, BillableInterface
{
    public function getBillingCustomerId(): string { return 'org_'.$this->organization->getId(); }
    public function getBillingEmail(): ?string { return $this->organization->getBillingEmail(); }
    public function getBillingName(): ?string { return $this->organization->getName(); }
}
```

### Write your own resolver

Implement `CustomerResolverInterface` and point `paykit.customer_resolver` at the service:

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

Returning `null` means "no customer": the attribute answers 401.
