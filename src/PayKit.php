<?php

declare(strict_types=1);

namespace PayKit\Bundle;

use PayKit\Bundle\Catalog\Catalog;
use PayKit\Bundle\Entity\Customer;
use PayKit\Bundle\Model\CheckResult;
use PayKit\Bundle\Model\CustomerView;
use PayKit\Bundle\Model\ReportResult;
use PayKit\Bundle\Model\SubscribeResult;
use PayKit\Bundle\Model\SyncResult;
use PayKit\Bundle\Service\CustomerService;
use PayKit\Bundle\Service\EntitlementService;
use PayKit\Bundle\Service\ProductSyncService;
use PayKit\Bundle\Service\SubscriptionService;
use PayKit\Bundle\Service\WebhookProcessor;

/**
 * Main entry point, mirroring PayKit's server API (`paykit.subscribe()`, `paykit.check()`...).
 * Autowire it as `PayKit\Bundle\PayKit` or use the `paykit` service id.
 */
class PayKit
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly CustomerService $customers,
        private readonly SubscriptionService $subscriptions,
        private readonly EntitlementService $entitlements,
        private readonly ProductSyncService $productSync,
        private readonly WebhookProcessor $webhooks,
    ) {
    }

    /** Subscribe, upgrade, downgrade, cancel (subscribe to the free plan) or resume. */
    public function subscribe(string $customerId, string $planId, ?string $successUrl = null, ?string $cancelUrl = null, bool $forceCheckout = false): SubscribeResult
    {
        return $this->subscriptions->subscribe($customerId, $planId, $successUrl, $cancelUrl, $forceCheckout);
    }

    public function check(string $customerId, string $featureId, int $required = 1): CheckResult
    {
        return $this->entitlements->check($customerId, $featureId, $required);
    }

    /** Shortcut for check(...)->allowed. */
    public function can(string $customerId, string $featureId, int $required = 1): bool
    {
        return $this->entitlements->check($customerId, $featureId, $required)->allowed;
    }

    public function report(string $customerId, string $featureId, int $amount = 1): ReportResult
    {
        return $this->entitlements->report($customerId, $featureId, $amount);
    }

    /** @param array<string, string>|null $metadata */
    public function upsertCustomer(string $id, ?string $email = null, ?string $name = null, ?array $metadata = null): Customer
    {
        return $this->customers->upsert($id, $email, $name, $metadata);
    }

    public function getCustomer(string $id): ?CustomerView
    {
        return $this->customers->get($id);
    }

    /**
     * @param list<string> $planIds
     *
     * @return array{data: list<Customer>, total: int, hasMore: bool}
     */
    public function listCustomers(int $limit = 50, int $offset = 0, array $planIds = []): array
    {
        return $this->customers->list($limit, $offset, $planIds);
    }

    public function deleteCustomer(string $id): void
    {
        $this->customers->delete($id);
    }

    public function customerPortal(string $customerId, string $returnUrl): string
    {
        return $this->customers->portal($customerId, $returnUrl);
    }

    /**
     * @param array<string, string|list<string>> $headers
     *
     * @return array{received: int, processed: int, duplicates: int}
     */
    public function handleWebhook(string $payload, array $headers): array
    {
        return $this->webhooks->handle($payload, $headers);
    }

    /** @return list<SyncResult> */
    public function push(bool $dryRun = false): array
    {
        return $this->productSync->sync($dryRun);
    }

    public function catalog(): Catalog
    {
        return $this->catalog;
    }
}
