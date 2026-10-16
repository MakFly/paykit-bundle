<?php

declare(strict_types=1);

namespace PayKit\Bundle\Model;

final readonly class ReportResult
{
    public function __construct(public bool $success, public ?Balance $balance)
    {
    }
}
