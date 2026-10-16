<?php

declare(strict_types=1);

namespace PayKit\Bundle\Service;

use PayKit\Bundle\Entity\Customer;
use PayKit\Bundle\Exception\PayKitException;
use PayKit\Bundle\Model\CustomerView;
use PayKit\Bundle\Provider\PaymentProviderInterface;
use PayKit\Bundle\Repository\BillingRepository;
use Psr\Clock\ClockInterface;

final class CustomerService
{
    public function __construct(
        private readonly BillingRepository $repository,
        private readonly SubscriptionService $subscriptions,
        private readonly EntitlementService $entitlements,
        private readonly PaymentProviderInterface $provider,
        private readonly ProviderCustomerLinker $linker,
        private readonly EventQueue $events,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Creates or updates a customer. New customers are put on every group's default plan.
     *
     * @param array<string, string>|null $metadata
     */
    public function upsert(string $id, ?string $email = null, ?string $name = null, ?array $metadata = null): Customer
    {
        if ('' === $id) {
            throw new \InvalidArgumentException('Customer id must not be empty.');
        }
        $now = $this->clock->now();
        $customer = $this->repository->customer($id);
        if (null === $customer) {
            $customer = new Customer($id, $now, $email, $name, $metadata);
            $this->repository->em()->persist($customer);
            $this->repository->em()->flush();
            $this->subscriptions->activateDefaultPlans($customer);
        } else {
            $wasDeleted = $customer->isDeleted();
            $changed = $email !== $customer->getEmail() || $name !== $customer->getName() || (null !== $metadata && $metadata !== $customer->getMetadata());
            $customer->update($email, $name, $metadata, $now);
            if ($changed && null !== $customer->getProviderCustomerId()) {
                $this->provider->updateCustomer($customer->getProviderCustomerId(), $customer->getEmail(), $customer->getName(), $customer->getMetadata() ?? []);
            }
            if ($wasDeleted) {
                $this->subscriptions->activateDefaultPlans($customer);
            }
        }
        $this->events->flush();

        return $customer;
    }

    public function get(string $id): ?CustomerView
    {
        $customer = $this->repository->customer($id);
        if (null === $customer || $customer->isDeleted()) {
            return null;
        }

        return new CustomerView(
            $customer,
            $this->repository->activeSubscriptions($id, $this->clock->now()),
            $this->entitlements->balances($id),
        );
    }

    /**
     * @param list<string> $planIds only customers actively subscribed to one of these plans
     *
     * @return array{data: list<Customer>, total: int, hasMore: bool}
     */
    public function list(int $limit = 50, int $offset = 0, array $planIds = []): array
    {
        return $this->repository->listCustomers(max(1, min($limit, 500)), max(0, $offset), $planIds, $this->clock->now());
    }

    /** Soft-deletes the customer and cancels its provider subscriptions immediately. */
    public function delete(string $id): void
    {
        $customer = $this->repository->customer($id) ?? throw PayKitException::customerNotFound($id);
        $this->subscriptions->endAll($customer);
        $customer->markDeleted($this->clock->now());
        $this->events->flush();
    }

    /** Returns a provider-hosted billing portal URL (Stripe Customer Portal). */
    public function portal(string $id, string $returnUrl): string
    {
        $customer = $this->repository->customer($id) ?? throw PayKitException::customerNotFound($id);
        $providerCustomerId = $this->linker->ensure($customer);
        $this->repository->em()->flush();

        return $this->provider->createPortalSession($providerCustomerId, $returnUrl);
    }
}
