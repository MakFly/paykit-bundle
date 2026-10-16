<?php

declare(strict_types=1);

namespace PayKit\Bundle\Catalog;

/**
 * A feature included in a plan. A null limit means unlimited (always the case for boolean features).
 */
final readonly class FeatureGrant
{
    public function __construct(
        public string $featureId,
        public FeatureType $type,
        public ?int $limit = null,
        public ?ResetInterval $reset = null,
    ) {
        if (FeatureType::Boolean === $type && (null !== $limit || null !== $reset)) {
            throw new \InvalidArgumentException(\sprintf('Boolean feature "%s" cannot have a limit or reset interval.', $featureId));
        }
        if (null !== $limit && $limit <= 0) {
            throw new \InvalidArgumentException(\sprintf('Feature "%s" limit must be a positive integer.', $featureId));
        }
        if (null !== $limit && null === $reset) {
            throw new \InvalidArgumentException(\sprintf('Metered feature "%s" with a limit needs a reset interval (day, week, month, year).', $featureId));
        }
    }

    public function isUnlimited(): bool
    {
        return null === $this->limit;
    }
}
