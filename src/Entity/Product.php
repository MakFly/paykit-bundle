<?php

declare(strict_types=1);

namespace PayKit\Bundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An immutable, versioned snapshot of a configured plan. Changing a plan's price or features
 * and running `paykit:push` creates a new version; existing subscribers keep their version.
 */
#[ORM\Entity]
#[ORM\Table(name: 'paykit_product')]
#[ORM\UniqueConstraint(name: 'paykit_product_plan_version_unique', columns: ['plan_id', 'version'])]
#[ORM\Index(name: 'paykit_product_provider_price_idx', columns: ['provider_price_id'])]
class Product
{
    #[ORM\Id]
    #[ORM\Column(name: 'internal_id', length: 40)]
    private string $internalId;

    #[ORM\Column(name: 'plan_id', length: 64)]
    private string $planId;

    #[ORM\Column(name: 'version')]
    private int $version;

    #[ORM\Column(name: 'name', length: 255)]
    private string $name;

    #[ORM\Column(name: 'plan_group', length: 64)]
    private string $group;

    #[ORM\Column(name: 'is_default')]
    private bool $isDefault;

    #[ORM\Column(name: 'price_amount', nullable: true)]
    private ?int $priceAmount;

    #[ORM\Column(name: 'price_currency', length: 3, nullable: true)]
    private ?string $priceCurrency;

    #[ORM\Column(name: 'price_interval', length: 16, nullable: true)]
    private ?string $priceInterval;

    #[ORM\Column(name: 'trial_days', nullable: true)]
    private ?int $trialDays;

    #[ORM\Column(name: 'hash', length: 64)]
    private string $hash;

    #[ORM\Column(name: 'provider_product_id', length: 255, nullable: true)]
    private ?string $providerProductId = null;

    #[ORM\Column(name: 'provider_price_id', length: 255, nullable: true)]
    private ?string $providerPriceId = null;

    /** @var Collection<int, ProductFeature> */
    #[ORM\OneToMany(targetEntity: ProductFeature::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    private Collection $features;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $planId, int $version, string $name, string $group, bool $isDefault, ?int $priceAmount, ?string $priceCurrency, ?string $priceInterval, ?int $trialDays, string $hash, \DateTimeImmutable $now)
    {
        $this->internalId = Id::generate('prod');
        $this->planId = $planId;
        $this->version = $version;
        $this->name = $name;
        $this->group = $group;
        $this->isDefault = $isDefault;
        $this->priceAmount = $priceAmount;
        $this->priceCurrency = $priceCurrency;
        $this->priceInterval = $priceInterval;
        $this->trialDays = $trialDays;
        $this->hash = $hash;
        $this->createdAt = $now;
        $this->features = new ArrayCollection();
    }

    public function getInternalId(): string { return $this->internalId; }
    public function getPlanId(): string { return $this->planId; }
    public function getVersion(): int { return $this->version; }
    public function getName(): string { return $this->name; }
    public function rename(string $name): void { $this->name = $name; }
    public function getGroup(): string { return $this->group; }
    public function isDefault(): bool { return $this->isDefault; }
    public function getPriceAmount(): ?int { return $this->priceAmount; }
    public function getPriceCurrency(): ?string { return $this->priceCurrency; }
    public function getPriceInterval(): ?string { return $this->priceInterval; }
    public function getTrialDays(): ?int { return $this->trialDays; }
    public function getHash(): string { return $this->hash; }
    public function getProviderProductId(): ?string { return $this->providerProductId; }
    public function getProviderPriceId(): ?string { return $this->providerPriceId; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function isFree(): bool { return null === $this->priceAmount; }
    public function isSynced(): bool { return $this->isFree() || null !== $this->providerPriceId; }

    public function linkProvider(string $productId, string $priceId): void
    {
        $this->providerProductId = $productId;
        $this->providerPriceId = $priceId;
    }

    /** @return Collection<int, ProductFeature> */
    public function getFeatures(): Collection { return $this->features; }

    public function addFeature(Feature $feature, ?int $limit, ?string $resetInterval): void
    {
        $this->features->add(new ProductFeature($this, $feature, $limit, $resetInterval));
    }
}
