<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Integration;

use PayKit\Bundle\Entity\WebhookEvent;
use PayKit\Bundle\Event\WebhookProcessedEvent;
use PayKit\Bundle\Exception\InvalidWebhookException;
use PayKit\Bundle\Provider\Dto\ProviderEvent;
use PayKit\Bundle\Provider\Dto\ProviderInvoice;
use PayKit\Bundle\Provider\Dto\ProviderPaymentMethod;
use PayKit\Bundle\Tests\PayKitTestCase;

final class WebhookTest extends PayKitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->payKit->push();
        $this->payKit->upsertCustomer('user_1');
        $this->payKit->customerPortal('user_1', 'https://app'); // links a provider customer
    }

    public function testRejectsInvalidSignature(): void
    {
        $this->expectException(InvalidWebhookException::class);
        $this->payKit->handleWebhook('{}', ['x-fake-signature' => 'forged']);
    }

    public function testDuplicateDeliveryIsProcessedOnce(): void
    {
        $event = new ProviderEvent('evt_dup', ProviderEvent::PAYMENT_METHOD_ATTACHED, $this->providerCustomerId('user_1'), paymentMethod: new ProviderPaymentMethod('pm_1', 'card', 'visa', '4242', 1, 2030, true));

        $first = $this->deliver($event);
        $second = $this->deliver($event);

        self::assertSame(1, $first['processed']);
        self::assertSame(['received' => 1, 'processed' => 0, 'duplicates' => 1], $second);
        self::assertCount(1, $this->dispatchedOf(WebhookProcessedEvent::class));
        self::assertTrue($this->repository->hasDefaultPaymentMethod('user_1'));
        $stored = $this->em->getRepository(WebhookEvent::class)->findOneBy(['providerEventId' => 'evt_dup']);
        self::assertSame(WebhookEvent::STATUS_PROCESSED, $stored?->getStatus());
    }

    public function testPaymentMethodDetachedRemovesDefault(): void
    {
        $this->deliver(new ProviderEvent('evt_a', ProviderEvent::PAYMENT_METHOD_ATTACHED, $this->providerCustomerId('user_1'), paymentMethod: new ProviderPaymentMethod('pm_1', 'card', isDefault: true)));
        $this->deliver(new ProviderEvent('evt_d', ProviderEvent::PAYMENT_METHOD_DETACHED, providerMethodId: 'pm_1'));

        self::assertFalse($this->repository->hasDefaultPaymentMethod('user_1'));
    }

    public function testInvoicesAreUpsertedAndLinkedToSubscription(): void
    {
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'pro');
        $subId = (string) $this->payKit->getCustomer('user_1')?->subscriptions[0]->getProviderSubscriptionId();
        $cus = $this->providerCustomerId('user_1');

        $this->deliver(new ProviderEvent('evt_i1', ProviderEvent::INVOICE_UPDATED, $cus, invoice: new ProviderInvoice('in_1', 'open', 1900, 'USD', 'https://inv', $subId)));
        $this->deliver(new ProviderEvent('evt_i2', ProviderEvent::INVOICE_UPDATED, $cus, invoice: new ProviderInvoice('in_1', 'paid', 1900, 'usd', 'https://inv', $subId)));

        $invoice = $this->repository->invoiceByProviderId('in_1');
        self::assertSame('paid', $invoice?->getStatus());
        self::assertSame('usd', $invoice->getCurrency());
        self::assertNotNull($invoice->getSubscriptionId());
    }

    public function testFailedEventIsRecordedAndRetriedOnRedelivery(): void
    {
        // Checkout completion for an unknown customer row: make processing blow up via a broken session.
        $this->em->getConnection()->insert('paykit_checkout_session', [
            'id' => 'cs_local', 'provider_session_id' => 'cs_broken', 'customer_id' => 'ghost', 'product_internal_id' => 'nope', 'created_at' => '2026-01-01 00:00:00',
        ]);
        $event = new ProviderEvent('evt_fail', ProviderEvent::CHECKOUT_COMPLETED, null, new \PayKit\Bundle\Provider\Dto\ProviderSubscription('sub_x', 'active'), checkoutSessionId: 'cs_broken');

        try {
            $this->deliver($event);
            self::fail('expected failure');
        } catch (\Throwable) {
        }
        $row = $this->em->getConnection()->fetchAssociative("SELECT status, attempts, error FROM paykit_webhook_event WHERE provider_event_id = 'evt_fail'");
        self::assertSame(WebhookEvent::STATUS_FAILED, $row['status'] ?? null);
        self::assertNotEmpty($row['error']);

        // Fix the data and let the provider redeliver.
        $this->em->getConnection()->delete('paykit_checkout_session', ['id' => 'cs_local']);
        $stats = $this->deliver($event);

        self::assertSame(1, $stats['processed']);
        $row = $this->em->getConnection()->fetchAssociative("SELECT status, attempts FROM paykit_webhook_event WHERE provider_event_id = 'evt_fail'");
        self::assertSame(WebhookEvent::STATUS_PROCESSED, $row['status'] ?? null);
        self::assertSame(2, (int) $row['attempts']);
    }

    public function testSubscriptionUpdateBeforeCheckoutCompletedIsNotDuplicated(): void
    {
        $this->payKit->subscribe('user_1', 'pro', 'https://app/ok');
        $sessionId = (string) array_key_last($this->provider->checkouts);
        $providerSub = $this->provider->completeCheckout($sessionId);
        $cus = $this->providerCustomerId('user_1');

        $this->deliver(new ProviderEvent('evt_u', ProviderEvent::SUBSCRIPTION_UPDATED, $cus, $providerSub));
        $this->deliver(new ProviderEvent('evt_c', ProviderEvent::CHECKOUT_COMPLETED, $cus, $providerSub, checkoutSessionId: $sessionId));

        self::assertSame(['pro'], $this->activePlans('user_1'));
        $count = (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM paykit_subscription WHERE provider_subscription_id = ?", [$providerSub->id]);
        self::assertSame(1, $count);
    }
}
