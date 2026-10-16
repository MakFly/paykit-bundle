<?php

declare(strict_types=1);

namespace PayKit\Bundle\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the billing customer of the current request (PayKit's `identify` option).
 * This is the trust boundary for the HTTP API, the attribute, the voter and Twig helpers.
 */
interface CustomerResolverInterface
{
    public function resolve(?Request $request = null): ?ResolvedCustomer;
}
