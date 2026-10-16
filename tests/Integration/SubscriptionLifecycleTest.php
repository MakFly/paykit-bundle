<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Integration;

use PayKit\Bundle\Event\SubscriptionActivatedEvent;
use PayKit\Bundle\Event\SubscriptionScheduledEvent;
use PayKit\Bundle\Exception\PayKitException;
use PayKit\Bundle\Model\SubscribeAction;
use PayKit\Bundle\Provider\Dto\ProviderEvent;
use PayKit\Bundle\Provider\Dto\ProviderSubscription;
use PayKit\Bundle\Tests\PayKitTestCase;

final class SubscriptionLifecycleTest extends PayKitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->payKit->push();
        $this->payKit->upsertCustomer('user_1', 'jane@example.com');
    }

    public function testPaidPlanWithoutPaymentMethodGoesThroughCheckout(): void
    {
        $result = $this->payKit->subscribe('user_1', 'pro', 'https://app/success', 'https://app/cancel');

        self::assertSame(SubscribeAction::Checkout, $result->action);
        self::assertStringStartsWith('https://checkout.fake/', (string) $result->paymentUrl);
        self::assertSame(['free'], $this->activePlans('user_1'), 'nothing changes until the provider confirms');

        $sessionId = (string) array_key_last($this->provider->checkouts);
        $this->deliver(new ProviderEvent('evt_1', ProviderEvent::CHECKOUT_COMPLETED, $this->providerCustomerId('user_1'), $this->provider->completeCheckout($sessionId), checkoutSessionId: $sessionId));

        self::assertSame(['pro'], $this->activePlans('user_1'));
        self::assertTrue($this->payKit->can('user_1', 'pro_models'));
        self::assertSame(2000, $this->payKit->check('user_1', 'messages')->balance?->limit);
    }

    public function testCheckoutRequiresSuccessUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->payKit->subscribe('user_1', 'pro');
    }

    public function testPaidPlanWithPaymentMethodActivatesImmediately(): void
    {
        $this->givePaymentMethod('user_1');
        $result = $this->payKit->subscribe('user_1', 'pro');

        self::assertSame(SubscribeAction::Activated, $result->action);
        self::assertNull($result->paymentUrl);
        self::assertSame(['pro'], $this->activePlans('user_1'));
        $activated = $this->dispatchedOf(SubscriptionActivatedEvent::class);
        self::assertSame('free', end($activated)->previous?->getPlanId());
    }

    public function testForceCheckout(): void
    {
        $this->givePaymentMethod('user_1');
        $result = $this->payKit->subscribe('user_1', 'pro', 'https://app/ok', forceCheckout: true);
        self::assertSame(SubscribeAction::Checkout, $result->action);
    }

    public function testSamePlanIsANoop(): void
    {
        self::assertSame(SubscribeAction::Noop, $this->payKit->subscribe('user_1', 'free')->action);
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'pro');
        $calls = \count($this->provider->calls);

        self::assertSame(SubscribeAction::Noop, $this->payKit->subscribe('user_1', 'pro')->action);
        self::assertCount($calls, $this->provider->calls);
    }

    public function testUpgradeIsImmediateAndKeepsProviderSubscription(): void
    {
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'pro');
        $subId = $this->payKit->getCustomer('user_1')?->subscriptions[0]->getProviderSubscriptionId();

        $result = $this->payKit->subscribe('user_1', 'ultra');

        self::assertSame(SubscribeAction::Upgraded, $result->action);
        self::assertSame(['ultra'], $this->activePlans('user_1'));
        self::assertSame($subId, $this->payKit->getCustomer('user_1')?->subscriptions[0]->getProviderSubscriptionId());
        self::assertContains('updateSubscription', $this->provider->calledMethods());
        self::assertTrue($this->payKit->check('user_1', 'messages')->balance?->unlimited);
    }

    public function testUpgradeWithoutPaymentMethodUsesCheckoutAndCancelsOldSubscription(): void
    {
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'pro');
        $oldSubId = (string) $this->payKit->getCustomer('user_1')?->subscriptions[0]->getProviderSubscriptionId();
        $this->repository->paymentMethodByProviderId('pm_user_1')?->markDeleted($this->clock->now());
        $this->em->flush();

        $result = $this->payKit->subscribe('user_1', 'ultra', 'https://app/ok');
        self::assertSame(SubscribeAction::Checkout, $result->action);

        $sessionId = (string) array_key_last($this->provider->checkouts);
        $this->deliver(new ProviderEvent('evt_c', ProviderEvent::CHECKOUT_COMPLETED, $this->providerCustomerId('user_1'), $this->provider->completeCheckout($sessionId), checkoutSessionId: $sessionId));

        self::assertSame(['ultra'], $this->activePlans('user_1'));
        self::assertContains(['cancelSubscription', [$oldSubId, false]], $this->provider->calls);
    }

    public function testDowngradeIsScheduledThenAppliedWhenProviderSwitchesPrice(): void
    {
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'ultra');

        $result = $this->payKit->subscribe('user_1', 'pro');

        self::assertSame(SubscribeAction::Scheduled, $result->action);
        $sub = $this->payKit->getCustomer('user_1')?->subscriptions[0];
        self::assertSame('ultra', $sub?->getPlanId(), 'stays on ultra until period end');
        self::assertSame('pro', $sub->getScheduledPlanId());
        self::assertCount(1, $this->dispatchedOf(SubscriptionScheduledEvent::class));

        // Period ends: the Stripe schedule swaps the price and sends subscription.updated.
        $this->clock->modify('2026-02-01 00:00:01');
        $proPrice = (string) $this->repository->latestProduct('pro')?->getProviderPriceId();
        $this->deliver(new ProviderEvent('evt_2', ProviderEvent::SUBSCRIPTION_UPDATED, $this->providerCustomerId('user_1'), new ProviderSubscription(
            (string) $sub->getProviderSubscriptionId(), 'active', $proPrice, false, new \DateTimeImmutable('2026-02-01'), new \DateTimeImmutable('2026-03-01'),
        )));

        self::assertSame(['pro'], $this->activePlans('user_1'));
        self::assertNull($this->payKit->getCustomer('user_1')?->subscriptions[0]->getScheduledPlanId());
        self::assertSame('2026-03-01', $this->payKit->check('user_1', 'messages')->balance?->resetAt?->format('Y-m-d'));
    }

    public function testChangingScheduledTargetReplacesIt(): void
    {
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'ultra');
        $this->payKit->subscribe('user_1', 'pro');
        $this->payKit->subscribe('user_1', 'free');

        $sub = $this->payKit->getCustomer('user_1')?->subscriptions[0];
        self::assertSame('free', $sub?->getScheduledPlanId());
        self::assertTrue($sub->isCancelAtPeriodEnd());
    }

    public function testCancelToFreeAtPeriodEnd(): void
    {
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'pro');
        $providerSubId = (string) $this->payKit->getCustomer('user_1')?->subscriptions[0]->getProviderSubscriptionId();

        $result = $this->payKit->subscribe('user_1', 'free');

        self::assertSame(SubscribeAction::Scheduled, $result->action);
        self::assertContains(['cancelSubscription', [$providerSubId, true]], $this->provider->calls);
        self::assertSame(['pro'], $this->activePlans('user_1'));
        self::assertTrue($this->payKit->can('user_1', 'pro_models'), 'access kept until period end');

        $this->deliver(new ProviderEvent('evt_3', ProviderEvent::SUBSCRIPTION_DELETED, $this->providerCustomerId('user_1'), providerSubscriptionId: $providerSubId));

        self::assertSame(['free'], $this->activePlans('user_1'));
        self::assertFalse($this->payKit->can('user_1', 'pro_models'));
    }

    public function testResumeClearsPendingCancellation(): void
    {
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'pro');
        $this->payKit->subscribe('user_1', 'free');

        $result = $this->payKit->subscribe('user_1', 'pro');

        self::assertSame(SubscribeAction::Resumed, $result->action);
        self::assertContains('resumeSubscription', $this->provider->calledMethods());
        $sub = $this->payKit->getCustomer('user_1')?->subscriptions[0];
        self::assertFalse($sub?->isCancelAtPeriodEnd());
        self::assertNull($sub->getScheduledPlanId());
    }

    public function testCancellationFromPortalIsMirrored(): void
    {
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'pro');
        $sub = $this->payKit->getCustomer('user_1')?->subscriptions[0];
        $price = (string) $sub?->getProduct()->getProviderPriceId();

        $this->deliver(new ProviderEvent('evt_p', ProviderEvent::SUBSCRIPTION_UPDATED, $this->providerCustomerId('user_1'), new ProviderSubscription((string) $sub->getProviderSubscriptionId(), 'active', $price, true)));

        $sub = $this->payKit->getCustomer('user_1')?->subscriptions[0];
        self::assertTrue($sub?->isCancelAtPeriodEnd());
        self::assertSame('free', $sub->getScheduledPlanId());
    }

    public function testPastDueRevokesAccessUntilPaid(): void
    {
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'pro');
        $sub = $this->payKit->getCustomer('user_1')?->subscriptions[0];
        $id = (string) $sub?->getProviderSubscriptionId();
        $price = (string) $sub->getProduct()->getProviderPriceId();

        $this->deliver(new ProviderEvent('evt_pd', ProviderEvent::SUBSCRIPTION_UPDATED, null, new ProviderSubscription($id, 'past_due', $price)));
        self::assertFalse($this->payKit->can('user_1', 'pro_models'));

        $this->deliver(new ProviderEvent('evt_ok', ProviderEvent::SUBSCRIPTION_UPDATED, null, new ProviderSubscription($id, 'active', $price)));
        self::assertTrue($this->payKit->can('user_1', 'pro_models'));
    }

    public function testUnknownAndUnsyncedPlans(): void
    {
        try {
            $this->payKit->subscribe('user_1', 'enterprise');
            self::fail('expected exception');
        } catch (PayKitException $e) {
            self::assertSame(PayKitException::PLAN_NOT_FOUND, $e->errorCode);
        }

        $this->em->getConnection()->executeStatement("UPDATE paykit_product SET provider_price_id = NULL WHERE plan_id = 'pro'");
        $this->em->clear();
        try {
            $this->payKit->subscribe('user_1', 'pro', 'https://app/ok');
            self::fail('expected exception');
        } catch (PayKitException $e) {
            self::assertSame(PayKitException::PLAN_NOT_SYNCED, $e->errorCode);
        }
    }
}
