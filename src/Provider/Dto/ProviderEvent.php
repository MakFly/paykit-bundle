<?php

declare(strict_types=1);

namespace PayKit\Bundle\Provider\Dto;

/**
 * Provider-neutral webhook event (PayKit's NormalizedWebhookEvent).
 */
final readonly class ProviderEvent
{
    public const CHECKOUT_COMPLETED = 'checkout.completed';
    public const SUBSCRIPTION_UPDATED = 'subscription.updated';
    public const SUBSCRIPTION_DELETED = 'subscription.deleted';
    public const INVOICE_UPDATED = 'invoice.updated';
    public const PAYMENT_METHOD_ATTACHED = 'payment_method.attached';
    public const PAYMENT_METHOD_DETACHED = 'payment_method.detached';

    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $providerCustomerId = null,
        public ?ProviderSubscription $subscription = null,
        public ?ProviderInvoice $invoice = null,
        public ?ProviderPaymentMethod $paymentMethod = null,
        public ?string $checkoutSessionId = null,
        public ?string $providerSubscriptionId = null,
        public ?string $providerMethodId = null,
        public array $raw = [],
    ) {
    }
}
