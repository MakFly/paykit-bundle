<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Integration;

use PayKit\Bundle\Event\UsageReportedEvent;
use PayKit\Bundle\Exception\PayKitException;
use PayKit\Bundle\Tests\PayKitTestCase;

final class EntitlementTest extends PayKitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->payKit->push();
        $this->payKit->upsertCustomer('user_1');
    }

    public function testBooleanFeatureFollowsPlan(): void
    {
        $check = $this->payKit->check('user_1', 'pro_models');
        self::assertFalse($check->allowed);
        self::assertNull($check->balance);

        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'pro');

        $check = $this->payKit->check('user_1', 'pro_models');
        self::assertTrue($check->allowed);
        self::assertNull($check->balance, 'boolean features carry no balance');
    }

    public function testMeteredCheckAndReport(): void
    {
        $check = $this->payKit->check('user_1', 'messages');
        self::assertTrue($check->allowed);
        self::assertSame(100, $check->balance?->limit);
        self::assertSame('2026-02-01', $check->balance->resetAt?->format('Y-m-d'));

        $report = $this->payKit->report('user_1', 'messages', 40);
        self::assertTrue($report->success);
        self::assertSame(60, $report->balance?->remaining);
        self::assertSame(40, $report->balance->used());

        self::assertFalse($this->payKit->check('user_1', 'messages', 61)->allowed);
        self::assertCount(1, $this->dispatchedOf(UsageReportedEvent::class));
    }

    public function testReportFailsWithoutDeductingWhenBalanceIsInsufficient(): void
    {
        $this->payKit->report('user_1', 'messages', 95);
        $report = $this->payKit->report('user_1', 'messages', 10);

        self::assertFalse($report->success);
        self::assertSame(5, $report->balance?->remaining);
        self::assertSame(5, $this->payKit->check('user_1', 'messages')->balance?->remaining);
    }

    public function testLazyResetAtPeriodBoundary(): void
    {
        $this->payKit->report('user_1', 'messages', 100);
        self::assertFalse($this->payKit->check('user_1', 'messages')->allowed);

        $this->clock->modify('2026-03-15 12:00:00');
        $check = $this->payKit->check('user_1', 'messages');

        self::assertTrue($check->allowed);
        self::assertSame(100, $check->balance?->remaining);
        self::assertSame('2026-04-01', $check->balance->resetAt?->format('Y-m-d'), 'next reset stays on the period boundary');
    }

    public function testGrantsStackAcrossGroups(): void
    {
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'extra_messages');

        $check = $this->payKit->check('user_1', 'messages');
        self::assertSame(600, $check->balance?->limit);
        self::assertEqualsCanonicalizing(['free', 'extra_messages'], $this->activePlans('user_1'));

        $report = $this->payKit->report('user_1', 'messages', 550);
        self::assertTrue($report->success, 'usage spans several grants');
        self::assertSame(50, $report->balance?->remaining);
    }

    public function testUnlimitedGrant(): void
    {
        $this->givePaymentMethod('user_1');
        $this->payKit->subscribe('user_1', 'ultra');

        $report = $this->payKit->report('user_1', 'messages', 1_000_000);
        self::assertTrue($report->success);
        self::assertTrue($report->balance?->unlimited);
    }

    public function testUnknownFeatureAndNonMeteredReportAreRejected(): void
    {
        try {
            $this->payKit->check('user_1', 'nope');
            self::fail('expected exception');
        } catch (PayKitException $e) {
            self::assertSame(PayKitException::FEATURE_NOT_FOUND, $e->errorCode);
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->payKit->report('user_1', 'pro_models');
    }

    public function testUnknownCustomerHasNoAccess(): void
    {
        self::assertFalse($this->payKit->can('ghost', 'messages'));
    }
}
