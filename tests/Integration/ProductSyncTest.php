<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Integration;

use PayKit\Bundle\Catalog\Catalog;
use PayKit\Bundle\Model\SyncResult;
use PayKit\Bundle\Tests\PayKitTestCase;

final class ProductSyncTest extends PayKitTestCase
{
    public function testFirstPushCreatesVersionOneAndProviderPrices(): void
    {
        $results = $this->payKit->push();

        self::assertSame(['created'], array_values(array_unique(array_map(static fn (SyncResult $r) => $r->action, $results))));
        $pro = $this->repository->latestProduct('pro');
        self::assertNotNull($pro);
        self::assertSame(1, $pro->getVersion());
        self::assertNotNull($pro->getProviderPriceId());
        self::assertSame(1900, $this->provider->prices[$pro->getProviderPriceId()]['amount']);
        self::assertNull($this->repository->latestProduct('free')?->getProviderPriceId(), 'free plans are never sent to the provider');
        self::assertCount(2, $pro->getFeatures());
    }

    public function testSecondPushIsANoop(): void
    {
        $this->payKit->push();
        $calls = \count($this->provider->calls);
        $results = $this->payKit->push();

        self::assertSame([SyncResult::UNCHANGED], array_values(array_unique(array_map(static fn (SyncResult $r) => $r->action, $results))));
        self::assertCount($calls, $this->provider->calls);
    }

    public function testDryRunWritesNothing(): void
    {
        $results = $this->payKit->push(dryRun: true);
        self::assertSame(SyncResult::CREATED, $results[0]->action);
        self::assertNull($this->repository->latestProduct('free'));
        self::assertSame([], $this->provider->calls);
    }

    public function testPriceChangeCreatesNewVersionAndPriceButKeepsProduct(): void
    {
        $this->payKit->push();
        $v1 = $this->repository->latestProduct('pro');
        self::assertNotNull($v1);

        $catalog = $this->catalogWith(['pro' => ['group' => 'base', 'price' => ['amount' => 25, 'interval' => 'month'], 'includes' => ['messages' => ['limit' => 2000, 'reset' => 'month']]]]);
        $results = $this->buildPayKit($catalog)->push();

        $v2 = $this->repository->latestProduct('pro');
        self::assertNotNull($v2);
        self::assertSame(2, $v2->getVersion());
        self::assertSame(SyncResult::CREATED, $results[0]->action);
        self::assertSame($v1->getProviderProductId(), $v2->getProviderProductId());
        self::assertNotSame($v1->getProviderPriceId(), $v2->getProviderPriceId());
    }

    public function testFeatureChangeKeepsPriceAndRenameUpdatesInPlace(): void
    {
        $this->payKit->push();
        $v1 = $this->repository->latestProduct('pro');
        self::assertNotNull($v1);

        $this->buildPayKit($this->catalogWith(['pro' => ['group' => 'base', 'price' => ['amount' => 19, 'interval' => 'month'], 'includes' => ['messages' => ['limit' => 3000, 'reset' => 'month']]]]))->push();
        $v2 = $this->repository->latestProduct('pro');
        self::assertSame(2, $v2?->getVersion());
        self::assertSame($v1->getProviderPriceId(), $v2->getProviderPriceId(), 'same price is reused');

        $results = $this->buildPayKit($this->catalogWith(['pro' => ['name' => 'Professional', 'group' => 'base', 'price' => ['amount' => 19, 'interval' => 'month'], 'includes' => ['messages' => ['limit' => 3000, 'reset' => 'month']]]]))->push();
        self::assertSame(SyncResult::UPDATED, $results[0]->action);
        self::assertSame(2, $this->repository->latestProduct('pro')?->getVersion());
        self::assertSame('Professional', $this->repository->latestProduct('pro')->getName());
    }

    /** @param array<string, array<string, mixed>> $plans */
    private function catalogWith(array $plans): Catalog
    {
        return Catalog::fromConfig(['messages' => ['type' => 'metered'], 'pro_models' => ['type' => 'boolean'], 'seats' => ['type' => 'metered']], $plans); // @phpstan-ignore argument.type
    }
}
