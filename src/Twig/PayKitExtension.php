<?php

declare(strict_types=1);

namespace PayKit\Bundle\Twig;

use PayKit\Bundle\Model\Balance;
use PayKit\Bundle\PayKit;
use PayKit\Bundle\Security\CustomerResolverInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 *     {% if paykit_can('pro_models') %}...{% endif %}
 *     {{ paykit_balance('messages').remaining }} messages left
 *     {% if paykit_plan('base') == 'free' %}Upgrade!{% endif %}
 */
final class PayKitExtension extends AbstractExtension
{
    public function __construct(
        private readonly PayKit $payKit,
        private readonly CustomerResolverInterface $resolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('paykit_can', $this->can(...)),
            new TwigFunction('paykit_balance', $this->balance(...)),
            new TwigFunction('paykit_plan', $this->plan(...)),
        ];
    }

    public function can(string $feature, ?string $customerId = null): bool
    {
        $customerId ??= $this->resolver->resolve()?->id;

        return null !== $customerId && $this->payKit->can($customerId, $feature);
    }

    public function balance(string $feature, ?string $customerId = null): ?Balance
    {
        $customerId ??= $this->resolver->resolve()?->id;

        return null === $customerId ? null : $this->payKit->check($customerId, $feature)->balance;
    }

    /** Current plan id in a group (default group: "default"). */
    public function plan(string $group = 'default', ?string $customerId = null): ?string
    {
        $customerId ??= $this->resolver->resolve()?->id;
        $view = null === $customerId ? null : $this->payKit->getCustomer($customerId);
        foreach ($view->subscriptions ?? [] as $subscription) {
            if ($subscription->getGroup() === $group) {
                return $subscription->getPlanId();
            }
        }

        return null;
    }
}
