---
title: First configuration
description: Declare features and plans, then use them from a controller.
sidebar:
  order: 2
---

Everything the bundle sells is declared in `config/packages/paykit.yaml`: the
**features** your app gates, and the **plans** that grant them.

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

Then push the catalog:

```bash
php bin/console paykit:push
```

## Use it from a controller

Inject `PayKit\Bundle\PayKit` (service alias `paykit`):

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

From here:

- [Plans and features](/guides/plans/) covers groups, add-ons, unlimited grants and versioning.
- [Subscriptions](/guides/subscriptions/) explains what each plan change does.
- [Configuration reference](/reference/configuration/) lists every option and its default.
