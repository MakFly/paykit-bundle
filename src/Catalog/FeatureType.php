<?php

declare(strict_types=1);

namespace PayKit\Bundle\Catalog;

enum FeatureType: string
{
    /** On/off access, e.g. "pro_models". */
    case Boolean = 'boolean';
    /** Consumable balance with a limit and a reset interval, e.g. "messages". */
    case Metered = 'metered';
}
