<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Integration;

use PayKit\Bundle\Event\CustomerUpdatedEvent;
use PayKit\Bundle\Exception\PayKitException;
use PayKit\Bundle\Tests\PayKitTestCase;

final class CustomerTest extends PayKitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->payKit->push();
    }

    public function testNewCustomerGetsDefaultPlanWithoutTouchingProvider(): void
    {
        $this->payKit->upsertCustomer('user_1', 'jane@example.com', 'Jane');

        self::assertSame(['free'], $this->activePlans('user_1'));
        self::assertNotContains('createCustomer', $this->provider->calledMethods(), 'provider customer is created lazily');
        $view = $this->payKit->getCustomer('user_1');
        self::assertSame(100, $view?->entitlements['messages']->remaining);
        self::assertCount(1, $this->dispatchedOf(CustomerUpdatedEvent::class));
    }

    public function testUpsertUpdatesDetailsAndSyncsLinkedProviderCustomer(): void
    {
        $this->payKit->upsertCustomer('user_1', 'old@example.com');
        $this->payKit->customerPortal('user_1', 'https://app/billing');
        $this->payKit->upsertCustomer('user_1', 'new@example.com');

        self::assertSame('new@example.com', $this->repository->customer('user_1')?->getEmail());
        self::assertContains('updateCustomer', $this->provider->calledMethods());
        self::assertSame(['free'], $this->activePlans('user_1'), 'no duplicate default subscription');
    }

    public function testListFiltersByPlanAndPaginates(): void
    {
        foreach (['a', 'b', 'c'] as $id) {
            $this->payKit->upsertCustomer($id);
        }
        $this->givePaymentMethod('b');
        $this->payKit->subscribe('b', 'pro');

        $page = $this->payKit->listCustomers(limit: 2);
        self::assertSame(3, $page['total']);
        self::assertCount(2, $page['data']);
        self::assertTrue($page['hasMore']);

        $pro = $this->payKit->listCustomers(planIds: ['pro']);
        self::assertSame(['b'], array_map(static fn ($c) => $c->getId(), $pro['data']));
    }

    public function testDeleteCancelsProviderSubscriptionsAndHidesCustomer(): void
    {
        $this->payKit->upsertCustomer('user_1');
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'pro');

        $this->payKit->deleteCustomer('user_1');

        self::assertNull($this->payKit->getCustomer('user_1'));
        self::assertContains('cancelSubscription', $this->provider->calledMethods());
        self::assertFalse($this->payKit->can('user_1', 'pro_models'));
        $this->expectException(PayKitException::class);
        $this->payKit->subscribe('user_1', 'pro');
    }

    public function testPortalCreatesProviderCustomerOnce(): void
    {
        $this->payKit->upsertCustomer('user_1');
        $url1 = $this->payKit->customerPortal('user_1', 'https://app');
        $url2 = $this->payKit->customerPortal('user_1', 'https://app');

        self::assertSame($url1, $url2);
        self::assertSame(1, \count(array_filter($this->provider->calledMethods(), static fn ($m) => 'createCustomer' === $m)));
    }
}
