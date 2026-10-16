<?php

declare(strict_types=1);

namespace App\Module;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A self-contained demo scenario: its own routes under /api/{id}, its own
 * fictional customer and its own plan group in config/packages/paykit.yaml.
 */
#[AutoconfigureTag(self::TAG)]
interface DemoModule
{
    public const TAG = 'app.demo_module';

    /** Route prefix and React module key, e.g. "shop". */
    public function id(): string;

    /** @return array{name: string, description: string} */
    public function describe(): array;
}
