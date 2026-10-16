<?php

declare(strict_types=1);

namespace PayKit\Bundle\Catalog;

use PayKit\Bundle\Exception\PayKitException;

/**
 * The billing catalog declared in configuration: features and plans (PayKit's `feature()` / `plan()`).
 */
final class Catalog
{
    /** @var array<string, FeatureDefinition> */
    private array $features = [];
    /** @var array<string, PlanDefinition> */
    private array $plans = [];

    /**
     * @param iterable<FeatureDefinition> $features
     * @param iterable<PlanDefinition>    $plans
     */
    public function __construct(iterable $features = [], iterable $plans = [])
    {
        foreach ($features as $feature) {
            if (isset($this->features[$feature->id])) {
                throw new \InvalidArgumentException(\sprintf('Duplicate feature "%s".', $feature->id));
            }
            $this->features[$feature->id] = $feature;
        }
        $defaults = [];
        foreach ($plans as $plan) {
            if (isset($this->plans[$plan->id])) {
                throw new \InvalidArgumentException(\sprintf('Duplicate plan "%s".', $plan->id));
            }
            foreach ($plan->includes as $grant) {
                $feature = $this->features[$grant->featureId] ?? throw new \InvalidArgumentException(\sprintf('Plan "%s" includes unknown feature "%s".', $plan->id, $grant->featureId));
                if ($feature->type !== $grant->type) {
                    throw new \InvalidArgumentException(\sprintf('Plan "%s" grants feature "%s" as %s but it is declared %s.', $plan->id, $grant->featureId, $grant->type->value, $feature->type->value));
                }
            }
            if ($plan->isDefault) {
                if (isset($defaults[$plan->group])) {
                    throw new \InvalidArgumentException(\sprintf('Group "%s" has more than one default plan ("%s", "%s").', $plan->group, $defaults[$plan->group], $plan->id));
                }
                $defaults[$plan->group] = $plan->id;
            }
            $this->plans[$plan->id] = $plan;
        }
    }

    /**
     * Builds the catalog from the bundle's normalized configuration array.
     *
     * @param array<string, array{type: string}> $features
     * @param array<string, array{name?: ?string, group?: string, default?: bool, trial_days?: ?int, price?: ?array{amount: int|float, interval: string}, includes?: array<string, ?array{limit?: ?int, reset?: ?string}>}> $plans
     */
    public static function fromConfig(array $features, array $plans, string $currency = 'usd'): self
    {
        $featureDefs = [];
        foreach ($features as $id => $f) {
            $featureDefs[$id] = new FeatureDefinition((string) $id, FeatureType::from($f['type']));
        }
        $planDefs = [];
        foreach ($plans as $id => $p) {
            $grants = [];
            foreach ($p['includes'] ?? [] as $featureId => $g) {
                $type = ($featureDefs[$featureId] ?? throw new \InvalidArgumentException(\sprintf('Plan "%s" includes unknown feature "%s".', $id, $featureId)))->type;
                $grants[] = new FeatureGrant(
                    (string) $featureId,
                    $type,
                    $g['limit'] ?? null,
                    isset($g['reset']) ? ResetInterval::from($g['reset']) : null,
                );
            }
            $price = $p['price'] ?? null;
            $planDefs[] = new PlanDefinition(
                id: (string) $id,
                name: $p['name'] ?? self::nameFromId((string) $id),
                group: $p['group'] ?? 'default',
                isDefault: $p['default'] ?? false,
                priceAmount: null !== $price ? (int) round($price['amount'] * 100) : null,
                priceCurrency: null !== $price ? strtolower($currency) : null,
                priceInterval: null !== $price ? PriceInterval::from($price['interval']) : null,
                includes: $grants,
                trialDays: $p['trial_days'] ?? null,
            );
        }

        return new self($featureDefs, $planDefs);
    }

    public function plan(string $id): PlanDefinition
    {
        return $this->plans[$id] ?? throw PayKitException::planNotFound($id);
    }

    public function hasPlan(string $id): bool
    {
        return isset($this->plans[$id]);
    }

    public function feature(string $id): FeatureDefinition
    {
        return $this->features[$id] ?? throw PayKitException::featureNotFound($id);
    }

    /** @return array<string, PlanDefinition> */
    public function plans(): array
    {
        return $this->plans;
    }

    /** @return array<string, FeatureDefinition> */
    public function features(): array
    {
        return $this->features;
    }

    /** @return array<string, PlanDefinition> keyed by group */
    public function defaultPlans(): array
    {
        $out = [];
        foreach ($this->plans as $plan) {
            if ($plan->isDefault) {
                $out[$plan->group] = $plan;
            }
        }

        return $out;
    }

    public function defaultPlanForGroup(string $group): ?PlanDefinition
    {
        return $this->defaultPlans()[$group] ?? null;
    }

    public static function assertValidId(string $id, string $kind): void
    {
        if (!preg_match('/^[a-z0-9_-]{1,64}$/', $id)) {
            throw new \InvalidArgumentException(\sprintf('Invalid %s id "%s": must be 1-64 chars of lowercase letters, digits, dashes or underscores.', $kind, $id));
        }
    }

    private static function nameFromId(string $id): string
    {
        return implode(' ', array_map(ucfirst(...), array_filter(preg_split('/[-_]/', $id) ?: [])));
    }
}
