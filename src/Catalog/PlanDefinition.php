<?php

declare(strict_types=1);

namespace PayKit\Bundle\Catalog;

final readonly class PlanDefinition
{
    /**
     * @param list<FeatureGrant> $includes
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $group,
        public bool $isDefault,
        public ?int $priceAmount,
        public ?string $priceCurrency,
        public ?PriceInterval $priceInterval,
        public array $includes = [],
        public ?int $trialDays = null,
    ) {
        Catalog::assertValidId($id, 'plan');
        if (null !== $priceAmount && ($priceAmount <= 0 || $priceAmount > 99_999_999)) {
            throw new \InvalidArgumentException(\sprintf('Plan "%s" price must be between 1 and 99999999 minor units.', $id));
        }
        if ((null === $priceAmount) !== (null === $priceInterval)) {
            throw new \InvalidArgumentException(\sprintf('Plan "%s" must define both price amount and interval, or neither.', $id));
        }
        if ($isDefault && null !== $priceAmount) {
            throw new \InvalidArgumentException(\sprintf('Default plan "%s" must be free.', $id));
        }
    }

    public function isFree(): bool
    {
        return null === $this->priceAmount;
    }

    public function grant(string $featureId): ?FeatureGrant
    {
        foreach ($this->includes as $grant) {
            if ($grant->featureId === $featureId) {
                return $grant;
            }
        }

        return null;
    }

    /**
     * Stable fingerprint of everything that requires a new product version when changed
     * (price and entitlements; the display name alone only updates in place).
     */
    public function hash(): string
    {
        $includes = array_map(static fn (FeatureGrant $g): array => [$g->featureId, $g->type->value, $g->limit, $g->reset?->value], $this->includes);
        usort($includes, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return hash('sha256', json_encode([
            'group' => $this->group,
            'default' => $this->isDefault,
            'amount' => $this->priceAmount,
            'currency' => $this->priceCurrency,
            'interval' => $this->priceInterval?->value,
            'trial' => $this->trialDays,
            'includes' => $includes,
        ], \JSON_THROW_ON_ERROR));
    }
}
