<?php

declare(strict_types=1);

namespace PayKit\Bundle\Model;

use PayKit\Bundle\Entity\Customer;
use PayKit\Bundle\Entity\Subscription;

/** Read model returned by PayKit::getCustomer(). */
final readonly class CustomerView
{
    /**
     * @param list<Subscription>     $subscriptions active subscriptions
     * @param array<string, Balance> $entitlements  keyed by feature id
     */
    public function __construct(
        public Customer $customer,
        public array $subscriptions,
        public array $entitlements,
    ) {
    }

    /** @return list<string> */
    public function planIds(): array
    {
        return array_map(static fn (Subscription $s): string => $s->getPlanId(), $this->subscriptions);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->customer->getId(),
            'email' => $this->customer->getEmail(),
            'name' => $this->customer->getName(),
            'subscriptions' => array_map(static fn (Subscription $s): array => [
                'planId' => $s->getPlanId(),
                'status' => $s->getStatus(),
                'cancelAtPeriodEnd' => $s->isCancelAtPeriodEnd(),
                'scheduledPlanId' => $s->getScheduledPlanId(),
                'currentPeriodStart' => $s->getCurrentPeriodStartAt()?->format(\DATE_ATOM),
                'currentPeriodEnd' => $s->getCurrentPeriodEndAt()?->format(\DATE_ATOM),
            ], $this->subscriptions),
            'entitlements' => array_map(static fn (Balance $b): array => $b->toArray(), $this->entitlements),
        ];
    }
}
