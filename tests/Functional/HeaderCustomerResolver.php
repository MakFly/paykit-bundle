<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Functional;

use PayKit\Bundle\Security\CustomerResolverInterface;
use PayKit\Bundle\Security\ResolvedCustomer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/** Test resolver: the customer id comes from the X-User header. */
final class HeaderCustomerResolver implements CustomerResolverInterface
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function resolve(?Request $request = null): ?ResolvedCustomer
    {
        $id = ($request ?? $this->requestStack->getCurrentRequest())?->headers->get('X-User');

        return null === $id ? null : new ResolvedCustomer($id, $id.'@example.com');
    }
}
