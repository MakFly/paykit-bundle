<?php

declare(strict_types=1);

namespace PayKit\Bundle\Security;

/**
 * Gates a controller (class or method) behind a feature.
 *
 *     #[RequiresFeature('pro_models')]
 *     #[RequiresFeature('messages', consume: 1)]   // check, then report usage after a 2xx response
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class RequiresFeature
{
    public function __construct(
        public string $feature,
        /** Units required to pass the check. */
        public int $required = 1,
        /** Units reported once the controller returned a successful response (0 = none). */
        public int $consume = 0,
        public int $statusCode = 402,
        public string $message = 'This feature is not included in your plan or its usage limit was reached.',
    ) {
    }
}
