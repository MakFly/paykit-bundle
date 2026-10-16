<?php

declare(strict_types=1);

namespace App\Module\Saas;

use App\Module\DemoModule;

final class SaasModule implements DemoModule
{
    // A fictional shared demo customer, never the signed-in operator's identity.
    public const CUSTOMER_ID = 'saas-customer';
    public const GROUP = 'saas';
    public const FEATURES = ['messages', 'pro_models'];

    public function id(): string
    {
        return 'saas';
    }

    public function describe(): array
    {
        return [
            'name' => 'Plume',
            'description' => 'Plume, un assistant d’écriture : chaque réécriture consomme un message, le modèle Expert demande un forfait Pro.',
        ];
    }
}
