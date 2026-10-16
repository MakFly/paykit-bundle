<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Unit;

use PayKit\Bundle\Exception\InvalidWebhookException;
use PayKit\Bundle\Provider\Dto\ProviderEvent;
use PayKit\Bundle\Provider\Stripe\StripeProvider;
use PHPUnit\Framework\TestCase;

final class StripeProviderTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';

    private function sign(string $payload, ?int $ts = null): string
    {
        $ts ??= time();

        return 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$payload, self::SECRET);
    }

    /** @param array<string, mixed> $object */
    private function payload(string $type, array $object): string
    {
        return json_encode(['id' => 'evt_123', 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]], \JSON_THROW_ON_ERROR);
    }

    public function testRejectsBadSignature(): void
    {
        $provider = new StripeProvider('sk_test', self::SECRET);
        $this->expectException(InvalidWebhookException::class);
        $provider->parseWebhook($this->payload('invoice.paid', []), ['stripe-signature' => 't=1,v1=deadbeef']);
    }

    public function testRejectsMissingSignature(): void
    {
        $this->expectException(InvalidWebhookException::class);
        (new StripeProvider('sk_test', self::SECRET))->parseWebhook('{}', []);
    }

    public function testRejectsReplayOutsideTolerance(): void
    {
        $payload = $this->payload('invoice.paid', []);
        $this->expectException(InvalidWebhookException::class);
        (new StripeProvider('sk_test', self::SECRET))->parseWebhook($payload, ['stripe-signature' => $this->sign($payload, time() - 3600)]);
    }

    public function testNormalizesSubscriptionUpdatedWithItemLevelPeriods(): void
    {
        $payload = $this->payload('customer.subscription.updated', [
            'id' => 'sub_1', 'object' => 'subscription', 'customer' => 'cus_1', 'status' => 'active',
            'cancel_at_period_end' => true, 'schedule' => null,
            'items' => ['data' => [['id' => 'si_1', 'price' => ['id' => 'price_pro'], 'current_period_start' => 1767225600, 'current_period_end' => 1769904000]]],
        ]);
        $events = (new StripeProvider('sk_test', self::SECRET))->parseWebhook($payload, ['stripe-signature' => $this->sign($payload)]);

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertSame(ProviderEvent::SUBSCRIPTION_UPDATED, $event->type);
        self::assertSame('evt_123', $event->id);
        self::assertSame('cus_1', $event->providerCustomerId);
        self::assertSame('price_pro', $event->subscription?->priceId);
        self::assertTrue($event->subscription->cancelAtPeriodEnd);
        self::assertSame('2026-01-01', $event->subscription->currentPeriodStart?->format('Y-m-d'));
        self::assertSame('2026-02-01', $event->subscription->currentPeriodEnd?->format('Y-m-d'));
    }

    public function testNormalizesInvoiceWithParentSubscription(): void
    {
        $payload = $this->payload('invoice.paid', [
            'id' => 'in_1', 'object' => 'invoice', 'customer' => 'cus_1', 'status' => 'paid', 'total' => 1900, 'currency' => 'usd',
            'hosted_invoice_url' => 'https://invoice', 'parent' => ['subscription_details' => ['subscription' => 'sub_1']],
        ]);
        $events = (new StripeProvider('sk_test', self::SECRET))->parseWebhook($payload, ['stripe-signature' => $this->sign($payload)]);

        self::assertSame(ProviderEvent::INVOICE_UPDATED, $events[0]->type);
        self::assertSame('sub_1', $events[0]->invoice?->subscriptionId);
        self::assertSame(1900, $events[0]->invoice->total);
    }

    public function testNormalizesPaymentMethodAndIgnoresUnknownEvents(): void
    {
        $provider = new StripeProvider('sk_test', self::SECRET);
        $payload = $this->payload('payment_method.attached', ['id' => 'pm_1', 'object' => 'payment_method', 'customer' => 'cus_1', 'type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 1, 'exp_year' => 2030]]);
        $events = $provider->parseWebhook($payload, ['stripe-signature' => $this->sign($payload)]);
        self::assertSame('4242', $events[0]->paymentMethod?->last4);

        $payload = $this->payload('charge.refunded', ['id' => 'ch_1']);
        self::assertSame([], $provider->parseWebhook($payload, ['stripe-signature' => $this->sign($payload)]));
    }
}
