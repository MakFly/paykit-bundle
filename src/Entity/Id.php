<?php

declare(strict_types=1);

namespace PayKit\Bundle\Entity;

/** @internal */
final class Id
{
    public static function generate(string $prefix): string
    {
        return $prefix.'_'.bin2hex(random_bytes(12));
    }
}
