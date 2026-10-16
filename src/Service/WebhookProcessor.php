<?php

declare(strict_types=1);

namespace PayKit\Bundle\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PayKit\Bundle\Entity\Id;
use PayKit\Bundle\Entity\Invoice;
use PayKit\Bundle\Entity\PaymentMethod;
use PayKit\Bundle\Entity\WebhookEvent;
use PayKit\Bundle\Event\WebhookProcessedEvent;
use PayKit\Bundle\Provider\Dto\ProviderEvent;
use PayKit\Bundle\Provider\PaymentProviderInterface;
use PayKit\Bundle\Repository\BillingRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Verifies, de-duplicates and applies provider webhooks.
 *
 * Idempotency: every provider event id is claimed with a unique-constrained insert before
 * processing. Processed events are skipped; failed ones are retried on redelivery; an event
 * stuck in "processing" longer than the stale window is reclaimed.
 */
final class WebhookProcessor
{
    private const TABLE = 'paykit_webhook_event';

    public function __construct(
        private readonly BillingRepository $repository,
        private readonly PaymentProviderInterface $provider,
        private readonly SubscriptionService $subscriptions,
        private readonly EventQueue $events,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $staleProcessingSeconds = 300,
    ) {
    }

    /**
     * @param array<string, string|list<string>> $headers
     *
     * @return array{received: int, processed: int, duplicates: int}
     */
    public function handle(string $payload, array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = \is_array($value) ? (string) ($value[0] ?? '') : $value;
        }
        $events = $this->provider->parseWebhook($payload, $normalized);
        $stats = ['received' => \count($events), 'processed' => 0, 'duplicates' => 0];

        foreach ($events as $event) {
            if (!$this->claim($event)) {
                ++$stats['duplicates'];
                $this->logger->info('PayKit webhook {id} skipped (duplicate)', ['id' => $event->id]);
                continue;
            }
            $this->process($event);
            ++$stats['processed'];
        }

        return $stats;
    }

    private function process(ProviderEvent $event): void
    {
        $connection = $this->repository->em()->getConnection();
        $connection->beginTransaction();
        try {
            $this->apply($event);
            $this->events->push(new WebhookProcessedEvent($event));
            $this->repository->em()->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->events->clear();
            $this->repository->em()->clear();
            $this->finish($event->id, WebhookEvent::STATUS_FAILED, $e->getMessage());
            $this->logger->error('PayKit webhook {id} ({type}) failed: {error}', ['id' => $event->id, 'type' => $event->type, 'error' => $e->getMessage(), 'exception' => $e]);
            throw $e;
        }
        $this->finish($event->id, WebhookEvent::STATUS_PROCESSED, null);
        $this->events->flush();
    }

    private function apply(ProviderEvent $event): void
    {
        $now = $this->clock->now();
        switch ($event->type) {
            case ProviderEvent::CHECKOUT_COMPLETED:
                if (null !== $event->checkoutSessionId) {
                    $this->subscriptions->completeCheckout($event->checkoutSessionId, $event->subscription);
                }
                break;
            case ProviderEvent::SUBSCRIPTION_UPDATED:
                if (null !== $event->subscription) {
                    $this->subscriptions->syncFromProvider($event->subscription, $event->providerCustomerId);
                }
                break;
            case ProviderEvent::SUBSCRIPTION_DELETED:
                $this->subscriptions->handleProviderDeleted((string) ($event->providerSubscriptionId ?? $event->subscription?->id));
                break;
            case ProviderEvent::INVOICE_UPDATED:
                $data = $event->invoice;
                $customer = null !== $event->providerCustomerId ? $this->repository->customerByProviderId($event->providerCustomerId) : null;
                if (null === $data || null === $customer) {
                    break;
                }
                $invoice = $this->repository->invoiceByProviderId($data->id);
                if (null === $invoice) {
                    $invoice = new Invoice($customer, $data->id, $now);
                    $this->repository->em()->persist($invoice);
                }
                $localSub = null !== $data->subscriptionId ? $this->repository->subscriptionByProviderId($data->subscriptionId) : null;
                $invoice->update($data->status, $data->total, $data->currency, $data->hostedUrl, $localSub?->getId(), $data->periodStart, $data->periodEnd);
                break;
            case ProviderEvent::PAYMENT_METHOD_ATTACHED:
                $data = $event->paymentMethod;
                $customer = null !== $event->providerCustomerId ? $this->repository->customerByProviderId($event->providerCustomerId) : null;
                if (null === $data || null === $customer) {
                    break;
                }
                $pm = $this->repository->paymentMethodByProviderId($data->id);
                if (null === $pm) {
                    $pm = new PaymentMethod($customer, $data->id, $data->type);
                    $this->repository->em()->persist($pm);
                }
                $pm->update($data->type, $data->brand, $data->last4, $data->expiryMonth, $data->expiryYear, $data->isDefault);
                break;
            case ProviderEvent::PAYMENT_METHOD_DETACHED:
                if (null !== $event->providerMethodId) {
                    $this->repository->paymentMethodByProviderId($event->providerMethodId)?->markDeleted($now);
                }
                break;
        }
    }

    /** Returns true when this worker owns the event and must process it. */
    private function claim(ProviderEvent $event): bool
    {
        $connection = $this->repository->em()->getConnection();
        $now = $this->clock->now();
        try {
            $connection->insert(self::TABLE, [
                'id' => Id::generate('whe'),
                'provider_event_id' => $event->id,
                'type' => $event->type,
                'payload' => json_encode($event->raw, \JSON_THROW_ON_ERROR),
                'status' => WebhookEvent::STATUS_PROCESSING,
                'attempts' => 1,
                'received_at' => $now->format('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            $stale = $now->modify(\sprintf('-%d seconds', $this->staleProcessingSeconds))->format('Y-m-d H:i:s');

            // Atomically re-claim failed events, or processing ones whose worker died.
            return 1 === (int) $connection->executeStatement(
                'UPDATE '.self::TABLE.' SET status = ?, attempts = attempts + 1, error = NULL, received_at = ? WHERE provider_event_id = ? AND (status = ? OR (status = ? AND received_at < ?))',
                [WebhookEvent::STATUS_PROCESSING, $now->format('Y-m-d H:i:s'), $event->id, WebhookEvent::STATUS_FAILED, WebhookEvent::STATUS_PROCESSING, $stale],
            );
        }
    }

    private function finish(string $providerEventId, string $status, ?string $error): void
    {
        $this->repository->em()->getConnection()->update(self::TABLE, [
            'status' => $status,
            'error' => null === $error ? null : mb_substr($error, 0, 4000),
            'processed_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], ['provider_event_id' => $providerEventId]);
    }
}
