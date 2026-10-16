<?php

declare(strict_types=1);

namespace PayKit\Bundle\Service;

use PayKit\Bundle\Catalog\Catalog;
use PayKit\Bundle\Catalog\FeatureType;
use PayKit\Bundle\Entity\Entitlement;
use PayKit\Bundle\Event\UsageReportedEvent;
use PayKit\Bundle\Model\Balance;
use PayKit\Bundle\Model\CheckResult;
use PayKit\Bundle\Model\ReportResult;
use PayKit\Bundle\Repository\BillingRepository;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Feature gating and usage metering. Balances reset lazily on read/write: no cron needed,
 * and the next reset is computed from the period boundary, not from when it was detected.
 */
final class EntitlementService
{
    public function __construct(
        private readonly BillingRepository $repository,
        private readonly Catalog $catalog,
        private readonly ClockInterface $clock,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function check(string $customerId, string $featureId, int $required = 1): CheckResult
    {
        $feature = $this->catalog->feature($featureId);
        $rows = $this->loadAndReset($customerId, $featureId);
        if ([] === $rows) {
            return new CheckResult(false, null);
        }
        if (FeatureType::Boolean === $feature->type) {
            return new CheckResult(true, null);
        }
        $balance = self::aggregate($rows);

        return new CheckResult($balance->unlimited || $balance->remaining >= $required, $balance);
    }

    /**
     * Records consumption. Fails (without deducting anything) when the total remaining balance
     * across all grants is insufficient. Deducts from the grants resetting soonest first.
     */
    public function report(string $customerId, string $featureId, int $amount = 1): ReportResult
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Usage amount must be a positive integer.');
        }
        if (FeatureType::Metered !== $this->catalog->feature($featureId)->type) {
            throw new \InvalidArgumentException(\sprintf('Feature "%s" is not metered.', $featureId));
        }

        $connection = $this->repository->em()->getConnection();
        $connection->beginTransaction();
        try {
            $result = $this->deduct($customerId, $featureId, $amount);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->dispatcher?->dispatch(new UsageReportedEvent($customerId, $featureId, $amount, $result));

        return $result;
    }

    private function deduct(string $customerId, string $featureId, int $amount): ReportResult
    {
        $rows = $this->loadAndReset($customerId, $featureId);
        if ([] === $rows) {
            return new ReportResult(false, null);
        }
        $balance = self::aggregate($rows);
        if ($balance->unlimited) {
            return new ReportResult(true, $balance);
        }
        if ($balance->remaining < $amount) {
            return new ReportResult(false, $balance);
        }

        $now = $this->clock->now();
        $left = $amount;
        foreach ($rows as $row) {
            $take = min($left, (int) $row->getBalance());
            if ($take <= 0) {
                continue;
            }
            if (!$this->repository->deductEntitlement($row->getId(), $take, $now)) {
                // Lost a race with a concurrent report: abort the whole deduction.
                throw new ConcurrentUsageException();
            }
            $left -= $take;
            if (0 === $left) {
                break;
            }
        }
        foreach ($rows as $row) {
            $this->repository->em()->refresh($row);
        }

        return new ReportResult(true, self::aggregate($rows));
    }

    /** @return array<string, Balance> balances of every feature the customer can access, keyed by feature id */
    public function balances(string $customerId): array
    {
        $byFeature = [];
        foreach ($this->repository->activeEntitlements($customerId, null, $this->clock->now()) as $row) {
            $byFeature[$row->getFeatureId()][] = $row;
        }
        $out = [];
        foreach ($byFeature as $featureId => $rows) {
            $out[(string) $featureId] = self::aggregate($this->reset($rows));
        }

        return $out;
    }

    /** @return list<Entitlement> */
    private function loadAndReset(string $customerId, string $featureId): array
    {
        return $this->reset($this->repository->activeEntitlements($customerId, $featureId, $this->clock->now()));
    }

    /**
     * @param list<Entitlement> $rows
     *
     * @return list<Entitlement>
     */
    private function reset(array $rows): array
    {
        $now = $this->clock->now();
        $changed = false;
        foreach ($rows as $row) {
            $changed = $row->resetIfDue($now) || $changed;
        }
        if ($changed) {
            $this->repository->em()->flush();
        }

        return $rows;
    }

    /** @param list<Entitlement> $rows */
    private static function aggregate(array $rows): Balance
    {
        $limit = 0;
        $remaining = 0;
        $resetAt = null;
        foreach ($rows as $row) {
            if ($row->isUnlimited()) {
                return Balance::unlimited();
            }
            $limit += (int) $row->getLimit();
            $remaining += (int) $row->getBalance();
            $next = $row->getNextResetAt();
            if (null !== $next && (null === $resetAt || $next < $resetAt)) {
                $resetAt = $next;
            }
        }

        return new Balance($limit, $remaining, $resetAt, false);
    }
}
