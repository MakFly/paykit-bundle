<?php

declare(strict_types=1);

namespace App\Module\Shop;

use App\Module\DemoModule;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

// Listed first: the e-commerce scenario is the default one in the React app.
#[AsTaggedItem(priority: 10)]
final class ShopModule implements DemoModule
{
    // A fictional shared demo customer, never the signed-in operator's identity.
    public const CUSTOMER_ID = 'shop-customer';
    public const GROUP = 'club';
    public const FEATURES = ['free_shipping', 'member_prices', 'express_shipping'];

    public function id(): string
    {
        return 'shop';
    }

    public function describe(): array
    {
        return [
            'name' => 'Établi',
            'description' => 'Une boutique d’objets de bureau. L’adhésion Club débloque la livraison offerte, les prix membres et l’express.',
        ];
    }
}
