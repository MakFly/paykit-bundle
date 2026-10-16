<?php

declare(strict_types=1);

namespace PayKit\Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Inbox record used for webhook idempotency and auditing. */
#[ORM\Entity]
#[ORM\Table(name: 'paykit_webhook_event')]
#[ORM\UniqueConstraint(name: 'paykit_webhook_event_provider_unique', columns: ['provider_event_id'])]
#[ORM\Index(name: 'paykit_webhook_event_status_idx', columns: ['status'])]
class WebhookEvent
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 40)]
    private string $id;

    #[ORM\Column(name: 'provider_event_id', length: 255)]
    private string $providerEventId;

    #[ORM\Column(name: 'type', length: 64)]
    private string $type;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'payload', type: Types::JSON)]
    private array $payload;

    #[ORM\Column(name: 'status', length: 16)]
    private string $status = self::STATUS_PROCESSING;

    #[ORM\Column(name: 'error', type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(name: 'attempts')]
    private int $attempts = 1;

    #[ORM\Column(name: 'received_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(name: 'processed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    /** @param array<string, mixed> $payload */
    public function __construct(string $providerEventId, string $type, array $payload, \DateTimeImmutable $now)
    {
        $this->id = Id::generate('whe');
        $this->providerEventId = $providerEventId;
        $this->type = $type;
        $this->payload = $payload;
        $this->receivedAt = $now;
    }

    public function getId(): string { return $this->id; }
    public function getProviderEventId(): string { return $this->providerEventId; }
    public function getType(): string { return $this->type; }
    /** @return array<string, mixed> */
    public function getPayload(): array { return $this->payload; }
    public function getStatus(): string { return $this->status; }
    public function getError(): ?string { return $this->error; }
    public function getAttempts(): int { return $this->attempts; }
    public function getReceivedAt(): \DateTimeImmutable { return $this->receivedAt; }
    public function getProcessedAt(): ?\DateTimeImmutable { return $this->processedAt; }

    public function retry(): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->error = null;
        ++$this->attempts;
    }

    public function markProcessed(\DateTimeImmutable $now): void
    {
        $this->status = self::STATUS_PROCESSED;
        $this->processedAt = $now;
        $this->error = null;
    }

    public function markFailed(string $error, \DateTimeImmutable $now): void
    {
        $this->status = self::STATUS_FAILED;
        $this->error = $error;
        $this->processedAt = $now;
    }
}
