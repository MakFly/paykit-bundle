<?php

declare(strict_types=1);

namespace App\Module\Shop;

use App\Module\Shared\Billing;
use App\Module\Shop\Entity\ShopOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/api/shop', format: 'json')]
final class ShopController
{
    public function __construct(
        private readonly Billing $billing,
        private readonly ProductCatalog $products,
        private readonly Pricing $pricing,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/bootstrap', methods: ['POST'])]
    public function bootstrap(): JsonResponse
    {
        $this->billing->ensureCustomer(ShopModule::CUSTOMER_ID, 'client@example.test', 'Client de démonstration');

        return new JsonResponse($this->snapshot());
    }

    #[Route('', methods: ['GET'])]
    public function state(): JsonResponse
    {
        return new JsonResponse($this->snapshot());
    }

    #[Route('/subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $result = $this->billing->subscribe(ShopModule::CUSTOMER_ID, ShopModule::GROUP, $request->toArray()['planId'] ?? null);

        return new JsonResponse(['result' => $result->toArray(), ...$this->snapshot()]);
    }

    /** Prices a cart without consuming an express credit. */
    #[Route('/quote', methods: ['POST'])]
    public function quote(Request $request): JsonResponse
    {
        $cart = $this->pricing->parse($request->toArray());

        return new JsonResponse(['result' => $this->pricing->quote(ShopModule::CUSTOMER_ID, $cart['lines'], $cart['shipping']), ...$this->snapshot()]);
    }

    /** Places the order. An express delivery consumes one Club credit when one is left. */
    #[Route('/checkout', methods: ['POST'])]
    public function checkout(Request $request): JsonResponse
    {
        $cart = $this->pricing->parse($request->toArray());
        $order = $this->em->wrapInTransaction(function () use ($cart): ShopOrder {
            $credit = 'express' === $cart['shipping']
                && $this->billing->payKit()->report(ShopModule::CUSTOMER_ID, 'express_shipping')->success;
            $order = new ShopOrder(ShopModule::CUSTOMER_ID, $this->pricing->quote(ShopModule::CUSTOMER_ID, $cart['lines'], $cart['shipping'], $credit));
            $this->em->persist($order);

            return $order;
        });

        return new JsonResponse(['result' => ['order' => $order->toArray()], ...$this->snapshot()], 201);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $payKit = $this->billing->payKit();
        $orders = $this->em->getRepository(ShopOrder::class)->findBy(['customerId' => ShopModule::CUSTOMER_ID], ['createdAt' => 'DESC'], 5);

        return [
            'customer' => $this->billing->customer(ShopModule::CUSTOMER_ID, ShopModule::GROUP, ShopModule::FEATURES),
            'plans' => $this->billing->plans(ShopModule::GROUP),
            'products' => $this->products->all(),
            'perks' => [
                'freeShipping' => $payKit->can(ShopModule::CUSTOMER_ID, 'free_shipping'),
                'memberPrices' => $payKit->can(ShopModule::CUSTOMER_ID, 'member_prices'),
                'expressCredit' => $payKit->can(ShopModule::CUSTOMER_ID, 'express_shipping'),
            ],
            'shippingRates' => ['standard' => Pricing::STANDARD_SHIPPING, 'express' => Pricing::EXPRESS_SHIPPING],
            'orders' => array_map(static fn (ShopOrder $order): array => $order->toArray(), $orders),
        ];
    }
}
