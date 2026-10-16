<?php

declare(strict_types=1);

namespace PayKit\Bundle\Provider;

use PayKit\Bundle\Exception\InvalidWebhookException;
use PayKit\Bundle\Provider\Dto\CheckoutRequest;
use PayKit\Bundle\Provider\Dto\CheckoutResult;
use PayKit\Bundle\Provider\Dto\ProductSyncRequest;
use PayKit\Bundle\Provider\Dto\ProviderEvent;
use PayKit\Bundle\Provider\Dto\ProviderProductRef;
use PayKit\Bundle\Provider\Dto\ProviderSubscription;

/**
 * Port between PayKit's billing logic and a payment provider (Stripe by default).
 * Implementations translate provider objects into the provider-neutral DTOs in Dto\.
 */
interface PaymentProviderInterface
{
    public function getId(): string;

    /** @param array<string, string> $metadata */
    public function createCustomer(string $customerId, ?string $email, ?string $name, array $metadata = []): string;

    /** @param array<string, string> $metadata */
    public function updateCustomer(string $providerCustomerId, ?string $email, ?string $name, array $metadata = []): void;

    public function deleteCustomer(string $providerCustomerId): void;

    /** Creates or reuses the provider product, and a new price when the amount/interval changed. */
    public function syncProduct(ProductSyncRequest $request): ProviderProductRef;

    public function createCheckout(CheckoutRequest $request): CheckoutResult;

    /** Starts a subscription charging the customer's default payment method. */
    public function createSubscription(string $providerCustomerId, string $priceId, ?int $trialDays = null): ProviderSubscription;

    /** Swaps the price immediately (upgrade), with proration. */
    public function updateSubscription(string $providerSubscriptionId, string $priceId): ProviderSubscription;

    /** Switches to $priceId at the end of the current period (downgrade). */
    public function scheduleSubscriptionChange(string $providerSubscriptionId, string $priceId, ?string $existingScheduleId = null): ProviderSubscription;

    public function cancelSubscription(string $providerSubscriptionId, bool $atPeriodEnd, ?string $existingScheduleId = null): ProviderSubscription;

    /** Clears a pending cancellation or scheduled change. */
    public function resumeSubscription(string $providerSubscriptionId, ?string $existingScheduleId = null): ProviderSubscription;

    public function createPortalSession(string $providerCustomerId, string $returnUrl): string;

    /**
     * Verifies the signature and normalizes the payload. Unknown event types yield an empty list.
     *
     * @param array<string, string> $headers lower-cased header names
     *
     * @return list<ProviderEvent>
     *
     * @throws InvalidWebhookException
     */
    public function parseWebhook(string $payload, array $headers): array;
}
