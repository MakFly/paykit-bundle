<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Unit;

use PayKit\Bundle\Catalog\Catalog;
use PayKit\Bundle\Catalog\FeatureType;
use PayKit\Bundle\Catalog\PriceInterval;
use PayKit\Bundle\Catalog\ResetInterval;
use PayKit\Bundle\Exception\PayKitException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CatalogTest extends TestCase
{
    public function testBuildsPlansFromConfig(): void
    {
        $catalog = Catalog::fromConfig(
            ['messages' => ['type' => 'metered'], 'pro_models' => ['type' => 'boolean']],
            [
                'free' => ['group' => 'base', 'default' => true, 'includes' => ['messages' => ['limit' => 100, 'reset' => 'month']]],
                'pro_plus' => ['group' => 'base', 'price' => ['amount' => 19.99, 'interval' => 'year'], 'includes' => ['pro_models' => null]],
            ],
            'EUR',
        );

        $pro = $catalog->plan('pro_plus');
        self::assertSame('Pro Plus', $pro->name);
        self::assertSame(1999, $pro->priceAmount);
        self::assertSame('eur', $pro->priceCurrency);
        self::assertSame(PriceInterval::Year, $pro->priceInterval);
        self::assertSame(FeatureType::Boolean, $pro->grant('pro_models')?->type);
        self::assertTrue($catalog->plan('free')->isFree());
        self::assertSame('free', $catalog->defaultPlanForGroup('base')?->id);
        self::assertNull($catalog->defaultPlanForGroup('other'));
    }

    public function testUnknownPlanThrowsDomainError(): void
    {
        $this->expectException(PayKitException::class);
        (new Catalog())->plan('nope');
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1: array<string, mixed>, 2: string}> */
    public static function invalidConfigs(): iterable
    {
        yield 'unknown feature' => [[], ['free' => ['includes' => ['x' => null]]], 'unknown feature'];
        yield 'boolean with limit' => [['f' => ['type' => 'boolean']], ['p' => ['includes' => ['f' => ['limit' => 3]]]], 'cannot have a limit'];
        yield 'limit without reset' => [['f' => ['type' => 'metered']], ['p' => ['includes' => ['f' => ['limit' => 3]]]], 'reset interval'];
        yield 'paid default' => [[], ['p' => ['default' => true, 'price' => ['amount' => 5, 'interval' => 'month']]], 'must be free'];
        yield 'two defaults' => [[], ['a' => ['default' => true], 'b' => ['default' => true]], 'more than one default'];
        yield 'bad id' => [[], ['Bad Id' => []], 'Invalid plan id'];
    }

    /**
     * @param array<string, mixed> $features
     * @param array<string, mixed> $plans
     */
    #[DataProvider('invalidConfigs')]
    public function testRejectsInvalidConfig(array $features, array $plans, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        Catalog::fromConfig($features, $plans); // @phpstan-ignore argument.type
    }

    public function testHashIgnoresNameButTracksPriceAndFeatures(): void
    {
        $make = static fn (string $name, float $amount, int $limit) => Catalog::fromConfig(
            ['m' => ['type' => 'metered']],
            ['p' => ['name' => $name, 'price' => ['amount' => $amount, 'interval' => 'month'], 'includes' => ['m' => ['limit' => $limit, 'reset' => 'day']]]],
        )->plan('p')->hash();

        self::assertSame($make('A', 10, 5), $make('B', 10, 5));
        self::assertNotSame($make('A', 10, 5), $make('A', 11, 5));
        self::assertNotSame($make('A', 10, 5), $make('A', 10, 6));
    }

    #[DataProvider('resetCases')]
    public function testResetIntervalArithmetic(ResetInterval $interval, string $from, string $expected): void
    {
        self::assertSame($expected, $interval->addTo(new \DateTimeImmutable($from))->format('Y-m-d H:i'));
    }

    /** @return iterable<array{ResetInterval, string, string}> */
    public static function resetCases(): iterable
    {
        yield [ResetInterval::Day, '2026-01-31 10:00', '2026-02-01 10:00'];
        yield [ResetInterval::Week, '2026-01-31 10:00', '2026-02-07 10:00'];
        yield [ResetInterval::Month, '2026-01-31 10:00', '2026-02-28 10:00'];
        yield [ResetInterval::Month, '2028-01-31 10:00', '2028-02-29 10:00'];
        yield [ResetInterval::Month, '2026-03-15 10:00', '2026-04-15 10:00'];
        yield [ResetInterval::Year, '2028-02-29 00:00', '2029-02-28 00:00'];
    }

    public function testNextAfterSkipsMissedPeriods(): void
    {
        $next = ResetInterval::Month->nextAfter(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-04-10'));
        self::assertSame('2026-05-01', $next->format('Y-m-d'));
    }
}
