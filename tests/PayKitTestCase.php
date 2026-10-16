<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests;

use Doctrine\ORM\EntityManager;
use PayKit\Bundle\Catalog\Catalog;
use PayKit\Bundle\Entity\PaymentMethod;
use PayKit\Bundle\PayKit;
use PayKit\Bundle\Provider\Dto\ProviderEvent;
use PayKit\Bundle\Repository\BillingRepository;
use PayKit\Bundle\Service\CustomerService;
use PayKit\Bundle\Service\EntitlementService;
use PayKit\Bundle\Service\EventQueue;
use PayKit\Bundle\Service\ProductSyncService;
use PayKit\Bundle\Service\ProviderCustomerLinker;
use PayKit\Bundle\Service\SubscriptionService;
use PayKit\Bundle\Service\WebhookProcessor;
use PayKit\Bundle\Testing\FakeProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class PayKitTestCase extends TestCase
{
    protected EntityManager $em;
    protected MockClock $clock;
    protected FakeProvider $provider;
    protected Catalog $catalog;
    protected EventDispatcher $dispatcher;
    protected BillingRepository $repository;
    protected PayKit $payKit;
    /** @var list<object> */
    protected array $dispatched = [];
    private int $webhookSeq = 0;

    protected function setUp(): void
    {
        $this->em = OrmFactory::create();
        $this->clock = new MockClock('2026-01-01 00:00:00', 'UTC');
        $this->provider = new FakeProvider(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->catalog = $this->buildCatalog();
        $this->dispatcher = new EventDispatcher();
        $this->dispatched = [];
        foreach ([
            \PayKit\Bundle\Event\CustomerUpdatedEvent::class,
            \PayKit\Bundle\Event\SubscriptionActivatedEvent::class,
            \PayKit\Bundle\Event\SubscriptionEndedEvent::class,
            \PayKit\Bundle\Event\SubscriptionScheduledEvent::class,
            \PayKit\Bundle\Event\WebhookProcessedEvent::class,
            \PayKit\Bundle\Event\UsageReportedEvent::class,
        ] as $eventClass) {
            $this->dispatcher->addListener($eventClass, function (object $e): void { $this->dispatched[] = $e; });
        }
        $this->payKit = $this->buildPayKit($this->catalog);
    }

    protected function buildPayKit(Catalog $catalog): PayKit
    {
        $this->repository = new BillingRepository($this->em);
        $queue = new EventQueue($this->repository, $this->dispatcher);
        $linker = new ProviderCustomerLinker($this->provider, $this->clock);
        $entitlements = new EntitlementService($this->repository, $catalog, $this->clock, $this->dispatcher);
        $subscriptions = new SubscriptionService($this->repository, $catalog, $this->provider, $linker, $queue, $this->clock);
        $customers = new CustomerService($this->repository, $subscriptions, $entitlements, $this->provider, $linker, $queue, $this->clock);
        $sync = new ProductSyncService($this->repository, $catalog, $this->provider, $this->clock);
        $webhooks = new WebhookProcessor($this->repository, $this->provider, $subscriptions, $queue, $this->clock);

        return new PayKit($catalog, $customers, $subscriptions, $entitlements, $sync, $webhooks);
    }

    /** The README example: free (default) / pro / ultra in group "base", plus an add-on group. */
    protected function buildCatalog(): Catalog
    {
        return Catalog::fromConfig(
            ['messages' => ['type' => 'metered'], 'pro_models' => ['type' => 'boolean'], 'seats' => ['type' => 'metered']],
            [
                'free' => ['group' => 'base', 'default' => true, 'includes' => ['messages' => ['limit' => 100, 'reset' => 'month']]],
                'pro' => ['group' => 'base', 'price' => ['amount' => 19, 'interval' => 'month'], 'includes' => ['messages' => ['limit' => 2000, 'reset' => 'month'], 'pro_models' => null]],
                'ultra' => ['group' => 'base', 'price' => ['amount' => 49, 'interval' => 'month'], 'includes' => ['messages' => [], 'pro_models' => null]],
                'extra_messages' => ['group' => 'addons', 'price' => ['amount' => 5, 'interval' => 'month'], 'includes' => ['messages' => ['limit' => 500, 'reset' => 'month']]],
            ],
        );
    }

    protected function givePaymentMethod(string $customerId): void
    {
        $customer = $this->repository->customer($customerId);
        self::assertNotNull($customer);
        $pm = new PaymentMethod($customer, 'pm_'.$customerId, 'card');
        $pm->update('card', 'visa', '4242', 12, 2030, true);
        $this->em->persist($pm);
        $this->em->flush();
    }

    /** Delivers provider events through the real webhook pipeline. */
    protected function deliver(ProviderEvent ...$events): array
    {
        $payload = 'payload-'.++$this->webhookSeq;
        $this->provider->queueWebhook($payload, ...$events);

        return $this->payKit->handleWebhook($payload, ['X-Fake-Signature' => 'valid']);
    }

    protected function providerCustomerId(string $customerId): string
    {
        return (string) $this->repository->customer($customerId)?->getProviderCustomerId();
    }

    /** @return list<string> */
    protected function activePlans(string $customerId): array
    {
        $this->em->clear();

        return $this->payKit->getCustomer($customerId)?->planIds() ?? [];
    }

    /** @param class-string $class */
    protected function dispatchedOf(string $class): array
    {
        return array_values(array_filter($this->dispatched, static fn (object $e): bool => $e instanceof $class));
    }
}
