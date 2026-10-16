<?php

declare(strict_types=1);

namespace PayKit\Bundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use PayKit\Bundle\Catalog\ResetInterval;

#[ORM\Entity]
#[ORM\Table(name: 'paykit_product_feature')]
class ProductFeature
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'features')]
    #[ORM\JoinColumn(name: 'product_internal_id', referencedColumnName: 'internal_id', nullable: false)]
    private Product $product;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Feature::class)]
    #[ORM\JoinColumn(name: 'feature_id', referencedColumnName: 'id', nullable: false)]
    private Feature $feature;

    #[ORM\Column(name: 'feature_limit', nullable: true)]
    private ?int $limit;

    #[ORM\Column(name: 'reset_interval', length: 16, nullable: true)]
    private ?string $resetInterval;

    public function __construct(Product $product, Feature $feature, ?int $limit, ?string $resetInterval)
    {
        $this->product = $product;
        $this->feature = $feature;
        $this->limit = $limit;
        $this->resetInterval = $resetInterval;
    }

    public function getProduct(): Product { return $this->product; }
    public function getFeature(): Feature { return $this->feature; }
    public function getLimit(): ?int { return $this->limit; }
    public function getResetInterval(): ?ResetInterval { return null !== $this->resetInterval ? ResetInterval::from($this->resetInterval) : null; }
}
