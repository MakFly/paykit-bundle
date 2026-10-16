<?php

declare(strict_types=1);

namespace PayKit\Bundle\Service;

use PayKit\Bundle\Event\CustomerUpdatedEvent;
use PayKit\Bundle\Repository\BillingRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Buffers domain events until the unit of work is flushed, so listeners always observe
 * persisted state. Collapses "customer updated" notifications to one per customer.
 *
 * @internal
 */
final class EventQueue
{
    /** @var list<object> */
    private array $events = [];
    /** @var array<string, true> */
    private array $touchedCustomers = [];

    public function __construct(
        private readonly BillingRepository $repository,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function push(object $event): void
    {
        $this->events[] = $event;
    }

    public function touchCustomer(string $customerId): void
    {
        $this->touchedCustomers[$customerId] = true;
    }

    /** Flushes the entity manager, then dispatches buffered events. */
    public function flush(): void
    {
        $this->repository->em()->flush();
        $events = $this->events;
        $customers = array_keys($this->touchedCustomers);
        $this->events = [];
        $this->touchedCustomers = [];
        if (null === $this->dispatcher) {
            return;
        }
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
        foreach ($customers as $customerId) {
            $this->dispatcher->dispatch(new CustomerUpdatedEvent((string) $customerId, $this->repository->currentSubscriptions((string) $customerId)));
        }
    }

    public function clear(): void
    {
        $this->events = [];
        $this->touchedCustomers = [];
    }
}
