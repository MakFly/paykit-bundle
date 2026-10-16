<?php

declare(strict_types=1);

namespace App\Tests\Module;

use App\Tests\ApiTestCase;

final class ShopApiTest extends ApiTestCase
{
    private const CART = ['items' => [['productId' => 'clavier-75', 'quantity' => 1], ['productId' => 'tapis-feutre', 'quantity' => 2]]];

    public function testModulesAreListed(): void
    {
        [, $data] = $this->call('/api/modules');
        self::assertEqualsCanonicalizing(['saas', 'shop'], array_column($data['modules'], 'id'));
        self::assertContains('/api/shop', array_column($data['modules'], 'basePath'));
    }

    public function testNonMemberPaysFullPriceAndShipping(): void
    {
        [, $initial] = $this->call('/api/shop/bootstrap', []);
        self::assertSame('club_none', $initial['customer']['subscriptions'][0]['planId']);
        self::assertSame(['club_none', 'club', 'club_plus'], array_column($initial['plans'], 'id'));
        self::assertSame([], $initial['orders']);

        [, $quote] = $this->call('/api/shop/quote', [...self::CART, 'shipping' => 'express']);
        self::assertSame(11900 + 2 * 2900, $quote['result']['subtotal']);
        self::assertSame(0, $quote['result']['discount']);
        self::assertSame(990, $quote['result']['shippingCost']);

        [$status, $order] = $this->call('/api/shop/checkout', [...self::CART, 'shipping' => 'standard']);
        self::assertSame(201, $status);
        self::assertSame(11900 + 2 * 2900 + 490, $order['result']['order']['total']);
        self::assertCount(1, $order['orders']);
    }

    public function testClubMembershipConsumesExpressCredits(): void
    {
        $this->call('/api/shop/bootstrap', []);
        [, $club] = $this->call('/api/shop/subscribe', ['planId' => 'club']);
        self::assertSame('activated', $club['result']['action']);
        self::assertTrue($club['perks']['freeShipping']);
        self::assertFalse($club['perks']['memberPrices']);

        [, $quote] = $this->call('/api/shop/quote', [...self::CART, 'shipping' => 'express']);
        self::assertSame(0, $quote['result']['shippingCost']);
        self::assertSame(2, $quote['customer']['entitlements']['express_shipping']['remaining'], 'A quote consumes nothing.');

        foreach ([0, 0] as $cost) {
            [, $order] = $this->call('/api/shop/checkout', [...self::CART, 'shipping' => 'express']);
            self::assertSame($cost, $order['result']['order']['shippingCost']);
        }
        [, $paid] = $this->call('/api/shop/checkout', [...self::CART, 'shipping' => 'express']);
        self::assertFalse($paid['result']['order']['expressCredit']);
        self::assertSame(990, $paid['result']['order']['shippingCost']);
        self::assertSame(0, $paid['customer']['entitlements']['express_shipping']['remaining']);

        [, $plus] = $this->call('/api/shop/subscribe', ['planId' => 'club_plus']);
        self::assertSame('upgraded', $plus['result']['action']);
        [, $member] = $this->call('/api/shop/checkout', [...self::CART, 'shipping' => 'express']);
        self::assertSame(10700 + 2 * 2600, $member['result']['order']['total']);
        self::assertSame(1200 + 2 * 300, $member['result']['order']['discount']);
    }

    public function testInvalidCartsAreRejectedWithoutAnOrder(): void
    {
        $this->call('/api/shop/bootstrap', []);
        foreach ([
            ['items' => []],
            ['items' => [['productId' => 'missing', 'quantity' => 1]]],
            ['items' => [['productId' => 'clavier-75', 'quantity' => 11]]],
            ['items' => [['productId' => 'clavier-75', 'quantity' => 6], ['productId' => 'clavier-75', 'quantity' => 5]]],
            [...self::CART, 'shipping' => 'drone'],
        ] as $cart) {
            [$status] = $this->call('/api/shop/checkout', $cart);
            self::assertSame(400, $status);
        }
        [$status] = $this->call('/api/shop/subscribe', ['planId' => 'pro']);
        self::assertSame(400, $status, 'A plan of another module is rejected.');
        [, $state] = $this->call('/api/shop');
        self::assertSame([], $state['orders']);
        self::assertSame('club_none', $state['customer']['subscriptions'][0]['planId']);
    }
}
