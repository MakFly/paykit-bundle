<?php

declare(strict_types=1);

namespace PayKit\Bundle\Model;

final readonly class Balance
{
    public function __construct(
        public int $limit,
        public int $remaining,
        public ?\DateTimeImmutable $resetAt,
        public bool $unlimited,
    ) {
    }

    public static function unlimited(): self
    {
        return new self(0, 0, null, true);
    }

    public function used(): int
    {
        return $this->unlimited ? 0 : $this->limit - $this->remaining;
    }

    /** @return array{limit: int, remaining: int, resetAt: ?string, unlimited: bool} */
    public function toArray(): array
    {
        return ['limit' => $this->limit, 'remaining' => $this->remaining, 'resetAt' => $this->resetAt?->format(\DATE_ATOM), 'unlimited' => $this->unlimited];
    }
}
