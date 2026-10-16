<?php

declare(strict_types=1);

namespace App\Module\Shop;

/**
 * Products sold by the shop. PayKit only bills subscriptions: the store owns its
 * catalog and orders, and asks PayKit what the Club membership unlocks.
 */
final class ProductCatalog
{
    /** Prices in cents. "tint" is the background of the product stage in the store. */
    private const PRODUCTS = [
        'casque-studio' => [
            'name' => 'Casque Studio', 'category' => 'Audio', 'icon' => 'headphones', 'tint' => '#C9D3D8',
            'price' => 14900, 'memberPrice' => 13400,
            'description' => 'Un casque fermé pour travailler au calme : réduction de bruit douce, coussinets en mousse à mémoire et 40 heures d’autonomie.',
            'highlights' => ['Bluetooth 5.3 et jack 3,5 mm', '40 h d’autonomie', 'Pliable, étui en feutre inclus'],
        ],
        'clavier-75' => [
            'name' => "Clavier 75\u{00A0}%", 'category' => 'Périphériques', 'icon' => 'keyboard', 'tint' => '#D8D2C4',
            'price' => 11900, 'memberPrice' => 10700,
            'description' => 'Un clavier mécanique compact au boîtier en aluminium. Les touches restent silencieuses et les interrupteurs se changent sans soudure.',
            'highlights' => ['Interrupteurs tactiles silencieux', 'Boîtier aluminium', 'Disposition AZERTY'],
        ],
        'souris-ergo' => [
            'name' => 'Souris verticale', 'category' => 'Périphériques', 'icon' => 'mouse', 'tint' => '#CBD5C4',
            'price' => 5900, 'memberPrice' => 5300,
            'description' => 'Une souris inclinée à 57° qui garde le poignet dans une position naturelle pendant les longues journées.',
            'highlights' => ['Inclinaison 57°', 'Capteur 4000 DPI', 'Recharge USB-C'],
        ],
        'lampe-led' => [
            'name' => 'Lampe d’architecte', 'category' => 'Éclairage', 'icon' => 'lamp', 'tint' => '#E3D3B0',
            'price' => 8900, 'memberPrice' => 7900,
            'description' => 'Un bras articulé en acier et une lumière chaude réglable, pour éclairer le plan de travail sans éblouir l’écran.',
            'highlights' => ['Température 2700 à 5000 K', 'Bras articulé en acier', 'Pince ou pied lesté'],
        ],
        'support-portable' => [
            'name' => 'Support portable', 'category' => 'Bureau', 'icon' => 'laptop', 'tint' => '#D2D0CC',
            'price' => 3900, 'memberPrice' => 3500,
            'description' => 'Une pièce d’aluminium pliée qui remonte l’écran à hauteur des yeux et libère de la place sous le portable.',
            'highlights' => ['Aluminium recyclé', 'Pour écrans de 11 à 16 pouces', 'Patins anti-rayures'],
        ],
        'tapis-feutre' => [
            'name' => 'Sous-main en feutre', 'category' => 'Bureau', 'icon' => 'square', 'tint' => '#BFC4C9',
            'price' => 2900, 'memberPrice' => 2600,
            'description' => 'Un sous-main en feutre de laine de 4 mm. Il amortit les bruits du clavier et protège le plateau.',
            'highlights' => ['Feutre de laine 4 mm', '90 × 40 cm', 'Dessous antidérapant'],
        ],
        'carnet-pointille' => [
            'name' => 'Carnet pointillé', 'category' => 'Papeterie', 'icon' => 'notebook', 'tint' => '#E6C9B8',
            'price' => 2400, 'memberPrice' => 2200,
            'description' => 'Un carnet A5 cousu à plat, au papier de 100 g qui ne laisse pas passer l’encre.',
            'highlights' => ['192 pages pointillées', 'Papier 100 g', 'Reliure cousue à plat'],
        ],
        'mug-gres' => [
            'name' => 'Tasse en grès', 'category' => 'Bureau', 'icon' => 'coffee', 'tint' => '#C8C0D6',
            'price' => 3200, 'memberPrice' => 2900,
            'description' => 'Une tasse tournée à la main dans un grès épais qui garde le café chaud plus longtemps.',
            'highlights' => ['35 cl', 'Grès émaillé', 'Passe au lave-vaisselle'],
        ],
    ];

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $products = [];
        foreach (self::PRODUCTS as $id => $product) {
            $products[] = ['id' => $id, ...$product];
        }

        return $products;
    }

    /** @return array{id: string, name: string, price: int, memberPrice: int}|null */
    public function find(string $id): ?array
    {
        return isset(self::PRODUCTS[$id]) ? ['id' => $id, ...self::PRODUCTS[$id]] : null;
    }
}
