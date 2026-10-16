<?php

declare(strict_types=1);

namespace PayKit\Bundle\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Default resolver: the logged-in user's identifier is the customer id. Users implementing
 * BillableInterface can provide a different id (e.g. their organization) plus email/name.
 */
final class SecurityUserCustomerResolver implements CustomerResolverInterface
{
    public function __construct(private readonly ?Security $security = null)
    {
    }

    public function resolve(?Request $request = null): ?ResolvedCustomer
    {
        $user = $this->security?->getUser();
        if (null === $user) {
            return null;
        }
        if ($user instanceof BillableInterface) {
            return new ResolvedCustomer($user->getBillingCustomerId(), $user->getBillingEmail(), $user->getBillingName());
        }

        return new ResolvedCustomer($user->getUserIdentifier());
    }
}
