<?php

declare(strict_types=1);

namespace PayKit\Bundle\Catalog;

enum PriceInterval: string
{
    case Month = 'month';
    case Year = 'year';
}
