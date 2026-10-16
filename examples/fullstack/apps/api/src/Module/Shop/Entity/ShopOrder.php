<?php

declare(strict_types=1);

namespace App\Module\Shop\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** An order of the demo store. Payment is simulated: PayKit only bills the Club membership. */
#[ORM\Entity]
#[ORM\Table(name: 'shop_order')]
#[ORM\Index(name: 'shop_order_customer_idx', columns: ['customer_id', 'created_at'])]
class ShopOrder
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private string $id;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $quote from Pricing::quote() */
    public function __construct(
        #[ORM\Column(name: 'customer_id', length: 191)] private string $customerId,
        #[ORM\Column(type: Types::JSON)] private array $quote,
    ) {
        $this->id = 'ord_'.bin2hex(random_bytes(12));
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'createdAt' => $this->createdAt->format(\DATE_ATOM), ...$this->quote];
    }
}
