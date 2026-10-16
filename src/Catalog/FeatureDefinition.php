<?php

declare(strict_types=1);

namespace PayKit\Bundle\Catalog;

final readonly class FeatureDefinition
{
    public function __construct(
        public string $id,
        public FeatureType $type,
    ) {
        Catalog::assertValidId($id, 'feature');
    }
}
