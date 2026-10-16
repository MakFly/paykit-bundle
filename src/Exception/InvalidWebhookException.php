<?php

declare(strict_types=1);

namespace PayKit\Bundle\Exception;

final class InvalidWebhookException extends PayKitException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct(self::INVALID_WEBHOOK, $message, $previous);
    }
}
