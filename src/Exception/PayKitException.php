<?php

declare(strict_types=1);

namespace PayKit\Bundle\Exception;

class PayKitException extends \RuntimeException
{
    public const PLAN_NOT_FOUND = 'PLAN_NOT_FOUND';
    public const PLAN_NOT_SYNCED = 'PLAN_NOT_SYNCED';
    public const FEATURE_NOT_FOUND = 'FEATURE_NOT_FOUND';
    public const CUSTOMER_NOT_FOUND = 'CUSTOMER_NOT_FOUND';
    public const PROVIDER_CUSTOMER_NOT_FOUND = 'PROVIDER_CUSTOMER_NOT_FOUND';
    public const INVALID_WEBHOOK = 'INVALID_WEBHOOK';
    public const PROVIDER_ERROR = 'PROVIDER_ERROR';

    public function __construct(public readonly string $errorCode, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function planNotFound(string $id): self
    {
        return new self(self::PLAN_NOT_FOUND, \sprintf('Plan "%s" not found.', $id));
    }

    public static function planNotSynced(string $id): self
    {
        return new self(self::PLAN_NOT_SYNCED, \sprintf('Plan "%s" is not synced with the provider. Run "bin/console paykit:push".', $id));
    }

    public static function featureNotFound(string $id): self
    {
        return new self(self::FEATURE_NOT_FOUND, \sprintf('Feature "%s" not found.', $id));
    }

    public static function customerNotFound(string $id): self
    {
        return new self(self::CUSTOMER_NOT_FOUND, \sprintf('Customer "%s" not found.', $id));
    }
}
