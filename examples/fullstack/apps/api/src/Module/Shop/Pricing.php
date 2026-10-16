<?php

declare(strict_types=1);

namespace App\Module\Shop;

use PayKit\Bundle\PayKit;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Computes a cart total from the product catalog and the customer's Club perks. */
final class Pricing
{
    public const STANDARD_SHIPPING = 490;
    public const EXPRESS_SHIPPING = 990;

    public function __construct(private readonly ProductCatalog $products, private readonly PayKit $payKit)
    {
    }

    /**
     * Validates a client cart. Quantities of a repeated product are merged.
     *
     * @return array{lines: array<string, int>, shipping: 'standard'|'express'}
     */
    public function parse(array $payload): array
    {
        $items = $payload['items'] ?? null;
        if (!is_array($items) || [] === $items || count($items) > 20) {
            throw new BadRequestHttpException('Le panier doit contenir entre 1 et 20 lignes.');
        }
        $lines = [];
        foreach ($items as $item) {
            $id = is_array($item) ? ($item['productId'] ?? null) : null;
            $quantity = is_array($item) ? ($item['quantity'] ?? null) : null;
            if (!is_string($id) || null === $this->products->find($id)) {
                throw new BadRequestHttpException('Produit inconnu dans le panier.');
            }
            if (!is_int($quantity) || $quantity < 1 || ($lines[$id] ?? 0) + $quantity > 10) {
                throw new BadRequestHttpException('La quantité d’un produit doit être un entier entre 1 et 10.');
            }
            $lines[$id] = ($lines[$id] ?? 0) + $quantity;
        }
        $shipping = $payload['shipping'] ?? 'standard';
        if (!in_array($shipping, ['standard', 'express'], true)) {
            throw new BadRequestHttpException('Le mode de livraison doit être "standard" ou "express".');
        }

        return ['lines' => $lines, 'shipping' => $shipping];
    }

    /**
     * @param array<string, int> $lines
     * @param bool|null $expressCredit null: ask PayKit without consuming a credit (quote)
     *
     * @return array<string, mixed>
     */
    public function quote(string $customerId, array $lines, string $shipping, ?bool $expressCredit = null): array
    {
        $memberPrices = $this->payKit->can($customerId, 'member_prices');
        $items = [];
        $subtotal = $discount = 0;
        foreach ($lines as $id => $quantity) {
            $product = $this->products->find($id) ?? throw new \LogicException('Unchecked product.');
            $unitPrice = $memberPrices ? $product['memberPrice'] : $product['price'];
            $items[] = ['productId' => $id, 'name' => $product['name'], 'quantity' => $quantity, 'unitPrice' => $unitPrice, 'total' => $unitPrice * $quantity];
            $subtotal += $product['price'] * $quantity;
            $discount += ($product['price'] - $unitPrice) * $quantity;
        }
        if ('express' === $shipping) {
            $expressCredit ??= $this->payKit->can($customerId, 'express_shipping');
            $shippingCost = $expressCredit ? 0 : self::EXPRESS_SHIPPING;
        } else {
            $expressCredit = false;
            $shippingCost = $this->payKit->can($customerId, 'free_shipping') ? 0 : self::STANDARD_SHIPPING;
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'shippingCost' => $shippingCost,
            'expressCredit' => $expressCredit,
            'total' => $subtotal - $discount + $shippingCost,
            'currency' => 'eur',
        ];
    }
}
