<?php

declare(strict_types=1);

namespace PayKit\Bundle\Provider\Stripe;

use PayKit\Bundle\Exception\InvalidWebhookException;
use PayKit\Bundle\Exception\PayKitException;
use PayKit\Bundle\Provider\Dto\CheckoutRequest;
use PayKit\Bundle\Provider\Dto\CheckoutResult;
use PayKit\Bundle\Provider\Dto\ProductSyncRequest;
use PayKit\Bundle\Provider\Dto\ProviderEvent;
use PayKit\Bundle\Provider\Dto\ProviderInvoice;
use PayKit\Bundle\Provider\Dto\ProviderPaymentMethod;
use PayKit\Bundle\Provider\Dto\ProviderProductRef;
use PayKit\Bundle\Provider\Dto\ProviderSubscription;
use PayKit\Bundle\Provider\PaymentProviderInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe adapter built on stripe/stripe-php. Handles both pre- and post-2025 ("basil") API
 * shapes for subscription periods and invoice/subscription links.
 */
final class StripeProvider implements PaymentProviderInterface
{
    private ?StripeClient $client;

    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
        ?StripeClient $client = null,
        private readonly int $webhookTolerance = Webhook::DEFAULT_TOLERANCE,
    ) {
        $this->client = $client;
    }

    public function getId(): string
    {
        return 'stripe';
    }

    public function createCustomer(string $customerId, ?string $email, ?string $name, array $metadata = []): string
    {
        return $this->call(fn () => $this->client()->customers->create(array_filter([
            'email' => $email,
            'name' => $name,
            'metadata' => ['paykit_customer_id' => $customerId] + $metadata,
        ], static fn ($v) => null !== $v))->id);
    }

    public function updateCustomer(string $providerCustomerId, ?string $email, ?string $name, array $metadata = []): void
    {
        $this->call(fn () => $this->client()->customers->update($providerCustomerId, array_filter([
            'email' => $email,
            'name' => $name,
            'metadata' => $metadata ?: null,
        ], static fn ($v) => null !== $v)));
    }

    public function deleteCustomer(string $providerCustomerId): void
    {
        $this->call(fn () => $this->client()->customers->delete($providerCustomerId));
    }

    public function syncProduct(ProductSyncRequest $request): ProviderProductRef
    {
        return $this->call(function () use ($request): ProviderProductRef {
            $stripe = $this->client();
            $metadata = ['paykit_plan_id' => $request->planId, 'paykit_version' => (string) $request->version];

            if (null !== $request->existingProductId) {
                $productId = $stripe->products->update($request->existingProductId, ['name' => $request->name, 'metadata' => $metadata])->id;
            } else {
                $productId = $stripe->products->create(['name' => $request->name, 'metadata' => $metadata])->id;
            }

            $priceId = $request->existingPriceId ?? $stripe->prices->create([
                'product' => $productId,
                'unit_amount' => $request->priceAmount,
                'currency' => $request->priceCurrency,
                'recurring' => ['interval' => $request->priceInterval],
                'metadata' => $metadata,
            ])->id;

            return new ProviderProductRef($productId, $priceId);
        });
    }

    public function createCheckout(CheckoutRequest $request): CheckoutResult
    {
        return $this->call(function () use ($request): CheckoutResult {
            $params = [
                'mode' => 'subscription',
                'customer' => $request->providerCustomerId,
                'line_items' => [['price' => $request->priceId, 'quantity' => 1]],
                'success_url' => $request->successUrl,
                'metadata' => $request->metadata,
                'subscription_data' => ['metadata' => $request->metadata],
            ];
            if (null !== $request->cancelUrl) {
                $params['cancel_url'] = $request->cancelUrl;
            }
            if (null !== $request->trialDays) {
                $params['subscription_data']['trial_period_days'] = $request->trialDays;
            }
            $session = $this->client()->checkout->sessions->create($params);

            return new CheckoutResult($session->id, (string) $session->url);
        });
    }

    public function createSubscription(string $providerCustomerId, string $priceId, ?int $trialDays = null): ProviderSubscription
    {
        return $this->call(function () use ($providerCustomerId, $priceId, $trialDays) {
            $params = [
                'customer' => $providerCustomerId,
                'items' => [['price' => $priceId]],
                'payment_behavior' => 'allow_incomplete',
                'expand' => ['latest_invoice'],
            ];
            if (null !== $trialDays) {
                $params['trial_period_days'] = $trialDays;
            }

            return self::normalizeSubscription($this->client()->subscriptions->create($params)->toArray());
        });
    }

    public function updateSubscription(string $providerSubscriptionId, string $priceId): ProviderSubscription
    {
        return $this->call(function () use ($providerSubscriptionId, $priceId) {
            $stripe = $this->client();
            $current = $stripe->subscriptions->retrieve($providerSubscriptionId)->toArray();
            if (!empty($current['schedule'])) {
                $stripe->subscriptionSchedules->release(\is_array($current['schedule']) ? $current['schedule']['id'] : $current['schedule']);
            }
            $itemId = $current['items']['data'][0]['id'] ?? throw new PayKitException(PayKitException::PROVIDER_ERROR, 'Stripe subscription has no items.');

            return self::normalizeSubscription($stripe->subscriptions->update($providerSubscriptionId, [
                'items' => [['id' => $itemId, 'price' => $priceId]],
                'cancel_at_period_end' => false,
                'proration_behavior' => 'always_invoice',
                'payment_behavior' => 'allow_incomplete',
                'expand' => ['latest_invoice'],
            ])->toArray());
        });
    }

    public function scheduleSubscriptionChange(string $providerSubscriptionId, string $priceId, ?string $existingScheduleId = null): ProviderSubscription
    {
        return $this->call(function () use ($providerSubscriptionId, $priceId, $existingScheduleId) {
            $stripe = $this->client();
            $subscription = $stripe->subscriptions->retrieve($providerSubscriptionId)->toArray();
            if (!empty($subscription['cancel_at_period_end'])) {
                $stripe->subscriptions->update($providerSubscriptionId, ['cancel_at_period_end' => false]);
            }
            $scheduleId = $existingScheduleId ?? $stripe->subscriptionSchedules->create(['from_subscription' => $providerSubscriptionId])->id;
            $schedule = $stripe->subscriptionSchedules->retrieve($scheduleId)->toArray();
            $currentPhase = $schedule['phases'][0] ?? throw new PayKitException(PayKitException::PROVIDER_ERROR, 'Stripe schedule has no phase.');
            $currentPrice = $currentPhase['items'][0]['price'] ?? null;
            $currentPrice = \is_array($currentPrice) ? $currentPrice['id'] : $currentPrice;

            $stripe->subscriptionSchedules->update($scheduleId, [
                'end_behavior' => 'release',
                'proration_behavior' => 'none',
                'phases' => [
                    ['items' => [['price' => $currentPrice, 'quantity' => 1]], 'start_date' => $currentPhase['start_date'], 'end_date' => $currentPhase['end_date']],
                    ['items' => [['price' => $priceId, 'quantity' => 1]], 'iterations' => 1],
                ],
            ]);

            $result = self::normalizeSubscription($stripe->subscriptions->retrieve($providerSubscriptionId)->toArray());

            return new ProviderSubscription($result->id, $result->status, $result->priceId, false, $result->currentPeriodStart, $result->currentPeriodEnd, null, null, $result->trialEnd, $scheduleId);
        });
    }

    public function cancelSubscription(string $providerSubscriptionId, bool $atPeriodEnd, ?string $existingScheduleId = null): ProviderSubscription
    {
        return $this->call(function () use ($providerSubscriptionId, $atPeriodEnd, $existingScheduleId) {
            $stripe = $this->client();
            if (null !== $existingScheduleId) {
                $stripe->subscriptionSchedules->release($existingScheduleId);
            }
            $sub = $atPeriodEnd
                ? $stripe->subscriptions->update($providerSubscriptionId, ['cancel_at_period_end' => true])
                : $stripe->subscriptions->cancel($providerSubscriptionId);

            return self::normalizeSubscription($sub->toArray());
        });
    }

    public function resumeSubscription(string $providerSubscriptionId, ?string $existingScheduleId = null): ProviderSubscription
    {
        return $this->call(function () use ($providerSubscriptionId, $existingScheduleId) {
            $stripe = $this->client();
            if (null !== $existingScheduleId) {
                $stripe->subscriptionSchedules->release($existingScheduleId);
            }

            return self::normalizeSubscription($stripe->subscriptions->update($providerSubscriptionId, ['cancel_at_period_end' => false])->toArray());
        });
    }

    public function createPortalSession(string $providerCustomerId, string $returnUrl): string
    {
        return $this->call(fn () => (string) $this->client()->billingPortal->sessions->create([
            'customer' => $providerCustomerId,
            'return_url' => $returnUrl,
        ])->url);
    }

    public function parseWebhook(string $payload, array $headers): array
    {
        $signature = $headers['stripe-signature'] ?? null;
        if (null === $signature || '' === $this->webhookSecret) {
            throw new InvalidWebhookException('Missing Stripe-Signature header or webhook secret.');
        }
        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret, $this->webhookTolerance);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            throw new InvalidWebhookException('Invalid Stripe webhook: '.$e->getMessage(), $e);
        }

        $raw = $event->toArray();
        $object = $raw['data']['object'] ?? [];
        $id = (string) $raw['id'];
        $type = (string) $raw['type'];

        $event = match (true) {
            'checkout.session.completed' === $type && 'subscription' === ($object['mode'] ?? null) => new ProviderEvent(
                id: $id,
                type: ProviderEvent::CHECKOUT_COMPLETED,
                providerCustomerId: self::ref($object['customer'] ?? null),
                subscription: $this->resolveSubscription($object['subscription'] ?? null),
                checkoutSessionId: (string) $object['id'],
                providerSubscriptionId: self::ref($object['subscription'] ?? null),
                raw: $raw,
            ),
            \in_array($type, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.paused', 'customer.subscription.resumed'], true) => new ProviderEvent(
                id: $id,
                type: ProviderEvent::SUBSCRIPTION_UPDATED,
                providerCustomerId: self::ref($object['customer'] ?? null),
                subscription: self::normalizeSubscription($object),
                providerSubscriptionId: (string) $object['id'],
                raw: $raw,
            ),
            'customer.subscription.deleted' === $type => new ProviderEvent(
                id: $id,
                type: ProviderEvent::SUBSCRIPTION_DELETED,
                providerCustomerId: self::ref($object['customer'] ?? null),
                subscription: self::normalizeSubscription($object),
                providerSubscriptionId: (string) $object['id'],
                raw: $raw,
            ),
            str_starts_with($type, 'invoice.') && isset($object['object']) && 'invoice' === $object['object'] => new ProviderEvent(
                id: $id,
                type: ProviderEvent::INVOICE_UPDATED,
                providerCustomerId: self::ref($object['customer'] ?? null),
                invoice: self::normalizeInvoice($object),
                providerSubscriptionId: self::invoiceSubscriptionId($object),
                raw: $raw,
            ),
            'payment_method.attached' === $type => new ProviderEvent(
                id: $id,
                type: ProviderEvent::PAYMENT_METHOD_ATTACHED,
                providerCustomerId: self::ref($object['customer'] ?? null),
                paymentMethod: self::normalizePaymentMethod($object),
                providerMethodId: (string) $object['id'],
                raw: $raw,
            ),
            'payment_method.detached' === $type => new ProviderEvent(
                id: $id,
                type: ProviderEvent::PAYMENT_METHOD_DETACHED,
                providerMethodId: (string) $object['id'],
                raw: $raw,
            ),
            default => null,
        };

        return null === $event ? [] : [$event];
    }

    /** @param array<string, mixed> $s */
    public static function normalizeSubscription(array $s): ProviderSubscription
    {
        $item = $s['items']['data'][0] ?? [];
        $price = $item['price'] ?? null;
        $invoice = isset($s['latest_invoice']) && \is_array($s['latest_invoice']) ? self::normalizeInvoice($s['latest_invoice']) : null;
        $actionUrl = (null !== $invoice && 'incomplete' === ($s['status'] ?? null)) ? $invoice->hostedUrl : null;

        return new ProviderSubscription(
            id: (string) $s['id'],
            status: (string) ($s['status'] ?? 'incomplete'),
            priceId: \is_array($price) ? ($price['id'] ?? null) : $price,
            cancelAtPeriodEnd: (bool) ($s['cancel_at_period_end'] ?? false) || !empty($s['cancel_at']),
            currentPeriodStart: self::ts($s['current_period_start'] ?? $item['current_period_start'] ?? null),
            currentPeriodEnd: self::ts($s['current_period_end'] ?? $item['current_period_end'] ?? null),
            canceledAt: self::ts($s['canceled_at'] ?? null),
            endedAt: self::ts($s['ended_at'] ?? null),
            trialEnd: self::ts($s['trial_end'] ?? null),
            scheduleId: self::ref($s['schedule'] ?? null),
            latestInvoice: $invoice,
            paymentActionUrl: $actionUrl,
        );
    }

    /** @param array<string, mixed> $i */
    public static function normalizeInvoice(array $i): ProviderInvoice
    {
        $line = $i['lines']['data'][0]['period'] ?? [];

        return new ProviderInvoice(
            id: (string) $i['id'],
            status: (string) ($i['status'] ?? 'draft'),
            total: (int) ($i['total'] ?? $i['amount_due'] ?? 0),
            currency: (string) ($i['currency'] ?? 'usd'),
            hostedUrl: $i['hosted_invoice_url'] ?? null,
            subscriptionId: self::invoiceSubscriptionId($i),
            periodStart: self::ts($line['start'] ?? $i['period_start'] ?? null),
            periodEnd: self::ts($line['end'] ?? $i['period_end'] ?? null),
        );
    }

    /** @param array<string, mixed> $pm */
    private static function normalizePaymentMethod(array $pm): ProviderPaymentMethod
    {
        $card = $pm['card'] ?? [];

        return new ProviderPaymentMethod(
            id: (string) $pm['id'],
            type: (string) ($pm['type'] ?? 'card'),
            brand: $card['brand'] ?? null,
            last4: $card['last4'] ?? null,
            expiryMonth: isset($card['exp_month']) ? (int) $card['exp_month'] : null,
            expiryYear: isset($card['exp_year']) ? (int) $card['exp_year'] : null,
            isDefault: true,
        );
    }

    /** @param array<string, mixed> $i */
    private static function invoiceSubscriptionId(array $i): ?string
    {
        return self::ref($i['subscription'] ?? $i['parent']['subscription_details']['subscription'] ?? null);
    }

    private function resolveSubscription(mixed $subscription): ?ProviderSubscription
    {
        if (\is_array($subscription)) {
            return self::normalizeSubscription($subscription);
        }
        if (!\is_string($subscription)) {
            return null;
        }

        return $this->call(fn () => self::normalizeSubscription($this->client()->subscriptions->retrieve($subscription)->toArray()));
    }

    private static function ref(mixed $value): ?string
    {
        if (\is_array($value)) {
            return isset($value['id']) ? (string) $value['id'] : null;
        }

        return \is_string($value) && '' !== $value ? $value : null;
    }

    private static function ts(mixed $value): ?\DateTimeImmutable
    {
        return is_numeric($value) ? (new \DateTimeImmutable('@'.(int) $value)) : null;
    }

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient($this->secretKey);
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    private function call(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (ApiErrorException $e) {
            throw new PayKitException(PayKitException::PROVIDER_ERROR, 'Stripe API error: '.$e->getMessage(), $e);
        }
    }
}
