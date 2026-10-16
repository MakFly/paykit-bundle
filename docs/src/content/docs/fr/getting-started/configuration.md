---
title: Première configuration
description: Déclarez les fonctionnalités et les offres, puis utilisez-les depuis un contrôleur.
sidebar:
  order: 2
---

Tout ce que le bundle vend est déclaré dans `config/packages/paykit.yaml` : les
**fonctionnalités** que votre application contrôle, et les **offres** qui les accordent.

```yaml
# config/packages/paykit.yaml
paykit:
    provider: stripe            # or "fake" (in memory, for tests and offline dev)
    currency: usd
    stripe:
        secret_key: '%env(STRIPE_SECRET_KEY)%'
        webhook_secret: '%env(STRIPE_WEBHOOK_SECRET)%'

    features:
        messages: metered       # counted, with a limit per period
        pro_models: boolean     # on or off

    plans:
        free:
            group: base
            default: true                           # new customers land here
            includes:
                messages: { limit: 100, reset: month }
        pro:
            group: base
            price: { amount: 19, interval: month }  # major units: 19 = $19.00
            trial_days: 14
            includes:
                messages: { limit: 2000, reset: month }
                pro_models: ~

when@test:
    paykit:
        provider: fake
```

Puis poussez le catalogue :

```bash
php bin/console paykit:push
```

## L'utiliser depuis un contrôleur

Injectez `PayKit\Bundle\PayKit` (alias de service `paykit`) :

```php
use PayKit\Bundle\PayKit;
use PayKit\Bundle\Model\SubscribeAction;

final class BillingController extends AbstractController
{
    #[Route('/upgrade', methods: ['POST'])]
    public function upgrade(PayKit $payKit): Response
    {
        $user = $this->getUser();
        $payKit->upsertCustomer($user->getUserIdentifier(), $user->getEmail());

        $result = $payKit->subscribe(
            $user->getUserIdentifier(),
            'pro',
            successUrl: $this->generateUrl('billing', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );

        return $result->action === SubscribeAction::Checkout
            ? $this->redirect($result->paymentUrl ?? $result->requiredActionUrl)
            : $this->redirectToRoute('billing');
    }
}
```

À partir de là :

- [Offres et fonctionnalités](/fr/guides/plans/) couvre les groupes, les extensions, les droits illimités et le versionnage.
- [Abonnements](/fr/guides/subscriptions/) explique ce que fait chaque changement d'offre.
- [Référence de configuration](/fr/reference/configuration/) liste chaque option et sa valeur par défaut.
