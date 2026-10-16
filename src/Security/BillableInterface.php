<?php

declare(strict_types=1);

namespace PayKit\Bundle\Security;

/** Implement on your User to control which PayKit customer it maps to. */
interface BillableInterface
{
    public function getBillingCustomerId(): string;

    public function getBillingEmail(): ?string;

    public function getBillingName(): ?string;
}
