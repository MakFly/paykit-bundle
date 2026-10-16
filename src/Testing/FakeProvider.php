<?php

declare(strict_types=1);

namespace PayKit\Bundle\Testing;

use PayKit\Bundle\Exception\InvalidWebhookException;
use PayKit\Bundle\Provider\Dto\CheckoutRequest;
use PayKit\Bundle\Provider\Dto\CheckoutResult;
use PayKit\Bundle\Provider\Dto\ProductSyncRequest;
use PayKit\Bundle\Provider\Dto\ProviderEvent;
use PayKit\Bundle\Provider\Dto\ProviderProductRef;
use PayKit\Bundle\Provider\Dto\ProviderSubscription;
use PayKit\Bundle\Provider\PaymentProviderInterface;

/**
 * In-memory provider for tests and local development (`paykit.provider: fake`).
 * Records every call and lets tests queue webhook events.
 */
final class FakeProvider implements PaymentProviderInterface
{
    /** @var list<array{0: string, 1: array<mixed>}> */
    public array $calls = [];
    /** @var array<string, array{email: ?string, name: ?string}> */
    public array $customers = [];
    /** @var array<string, array{amount: int, interval: string, product: string}> */
    public array $prices = [];
    /** @var array<string, ProviderSubscription> */
    public array $subscriptions = [];
    /** @var array<string, CheckoutRequest> */
    public array $checkouts = [];
    /** @var array<string, list<ProviderEvent>> payload => events */
    private array $webhooks = [];
    private int $seq = 0;

    public function __construct(private readonly \DateTimeImmutable $periodAnchor = new \DateTimeImmutable('2026-01-01T00:00:00Z'))
    {
    }

    public function getId(): string
    {
        return 'fake';
    }

    public function createCustomer(string $customerId, ?string $email, ?string $name, array $metadata = []): string
    {
        $id = $this->next('cus');
        $this->customers[$id] = ['email' => $email, 'name' => $name];
        $this->calls[] = ['createCustomer', [$customerId]];

        return $id;
    }

    public function updateCustomer(string $providerCustomerId, ?string $email, ?string $name, array $metadata = []): void
    {
        $this->customers[$providerCustomerId] = ['email' => $email, 'name' => $name];
        $this->calls[] = ['updateCustomer', [$providerCustomerId]];
    }

    public function deleteCustomer(string $providerCustomerId): void
    {
        unset($this->customers[$providerCustomerId]);
        $this->calls[] = ['deleteCustomer', [$providerCustomerId]];
    }

    public function syncProduct(ProductSyncRequest $request): ProviderProductRef
    {
        $productId = $request->existingProductId ?? $this->next('prod');
        $priceId = $request->existingPriceId ?? $this->next('price');
        $this->prices[$priceId] = ['amount' => $request->priceAmount, 'interval' => $request->priceInterval, 'product' => $productId];
        $this->calls[] = ['syncProduct', [$request->planId, $request->version]];

        return new ProviderProductRef($productId, $priceId);
    }

    public function createCheckout(CheckoutRequest $request): CheckoutResult
    {
        $id = $this->next('cs');
        $this->checkouts[$id] = $request;
        $this->calls[] = ['createCheckout', [$request->priceId]];

        return new CheckoutResult($id, 'https://checkout.fake/'.$id);
    }

    public function createSubscription(string $providerCustomerId, string $priceId, ?int $trialDays = null): ProviderSubscription
    {
        $sub = $this->makeSubscription($this->next('sub'), $priceId, null !== $trialDays ? 'trialing' : 'active');
        $this->calls[] = ['createSubscription', [$providerCustomerId, $priceId]];

        return $sub;
    }

    /** Simulates the subscription Stripe creates when a checkout session completes. */
    public function completeCheckout(string $sessionId): ProviderSubscription
    {
        $checkout = $this->checkouts[$sessionId] ?? throw new \LogicException("Unknown checkout $sessionId");

        return $this->makeSubscription($this->next('sub'), $checkout->priceId, 'active');
    }

    public function updateSubscription(string $providerSubscriptionId, string $priceId): ProviderSubscription
    {
        $this->calls[] = ['updateSubscription', [$providerSubscriptionId, $priceId]];

        return $this->makeSubscription($providerSubscriptionId, $priceId, 'active');
    }

    public function scheduleSubscriptionChange(string $providerSubscriptionId, string $priceId, ?string $existingScheduleId = null): ProviderSubscription
    {
        $this->calls[] = ['scheduleSubscriptionChange', [$providerSubscriptionId, $priceId]];
        $current = $this->subscriptions[$providerSubscriptionId];

        return $this->subscriptions[$providerSubscriptionId] = new ProviderSubscription($current->id, $current->status, $current->priceId, false, $current->currentPeriodStart, $current->currentPeriodEnd, scheduleId: $existingScheduleId ?? $this->next('sched'));
    }

    public function cancelSubscription(string $providerSubscriptionId, bool $atPeriodEnd, ?string $existingScheduleId = null): ProviderSubscription
    {
        $this->calls[] = ['cancelSubscription', [$providerSubscriptionId, $atPeriodEnd]];
        $current = $this->subscriptions[$providerSubscriptionId];

        return $this->subscriptions[$providerSubscriptionId] = new ProviderSubscription(
            $current->id,
            $atPeriodEnd ? $current->status : 'canceled',
            $current->priceId,
            $atPeriodEnd,
            $current->currentPeriodStart,
            $current->currentPeriodEnd,
            $this->periodAnchor,
            $atPeriodEnd ? null : $this->periodAnchor,
        );
    }

    public function resumeSubscription(string $providerSubscriptionId, ?string $existingScheduleId = null): ProviderSubscription
    {
        $this->calls[] = ['resumeSubscription', [$providerSubscriptionId]];
        $current = $this->subscriptions[$providerSubscriptionId];

        return $this->subscriptions[$providerSubscriptionId] = new ProviderSubscription($current->id, 'active', $current->priceId, false, $current->currentPeriodStart, $current->currentPeriodEnd);
    }

    public function createPortalSession(string $providerCustomerId, string $returnUrl): string
    {
        $this->calls[] = ['createPortalSession', [$providerCustomerId]];

        return 'https://portal.fake/'.$providerCustomerId;
    }

    /** Registers events returned when parseWebhook() receives $payload with header "x-fake-signature: valid". */
    public function queueWebhook(string $payload, ProviderEvent ...$events): void
    {
        $this->webhooks[$payload] = array_values($events);
    }

    public function parseWebhook(string $payload, array $headers): array
    {
        if ('valid' !== ($headers['x-fake-signature'] ?? null)) {
            throw new InvalidWebhookException('Invalid fake signature.');
        }

        return $this->webhooks[$payload] ?? [];
    }

    /** @return list<string> */
    public function calledMethods(): array
    {
        return array_map(static fn (array $c): string => $c[0], $this->calls);
    }

    private function makeSubscription(string $id, string $priceId, string $status): ProviderSubscription
    {
        return $this->subscriptions[$id] = new ProviderSubscription(
            id: $id,
            status: $status,
            priceId: $priceId,
            currentPeriodStart: $this->periodAnchor,
            currentPeriodEnd: $this->periodAnchor->modify('+1 month'),
        );
    }

    private function next(string $prefix): string
    {
        return \sprintf('%s_fake_%d', $prefix, ++$this->seq);
    }
}
