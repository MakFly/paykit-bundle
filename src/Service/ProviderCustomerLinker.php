<?php

declare(strict_types=1);

namespace PayKit\Bundle\Service;

use PayKit\Bundle\Entity\Customer;
use PayKit\Bundle\Provider\PaymentProviderInterface;
use Psr\Clock\ClockInterface;

/** Lazily creates the provider-side customer the first time money is involved. */
final class ProviderCustomerLinker
{
    public function __construct(
        private readonly PaymentProviderInterface $provider,
        private readonly ClockInterface $clock,
    ) {
    }

    public function ensure(Customer $customer): string
    {
        if (null !== $id = $customer->getProviderCustomerId()) {
            return $id;
        }
        $id = $this->provider->createCustomer($customer->getId(), $customer->getEmail(), $customer->getName(), $customer->getMetadata() ?? []);
        $customer->linkProviderCustomer($id, $this->clock->now());

        return $id;
    }
}
