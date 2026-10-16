<?php

declare(strict_types=1);

namespace PayKit\Bundle\Service;

use PayKit\Bundle\Catalog\Catalog;
use PayKit\Bundle\Catalog\PlanDefinition;
use PayKit\Bundle\Entity\Feature;
use PayKit\Bundle\Entity\Product;
use PayKit\Bundle\Model\SyncResult;
use PayKit\Bundle\Provider\Dto\ProductSyncRequest;
use PayKit\Bundle\Provider\PaymentProviderInterface;
use PayKit\Bundle\Repository\BillingRepository;
use Psr\Clock\ClockInterface;

/**
 * Pushes configured plans to the database and the provider (PayKit's `paykitjs push`).
 * Price/feature changes create a new immutable product version; renames update in place.
 */
final class ProductSyncService
{
    public function __construct(
        private readonly BillingRepository $repository,
        private readonly Catalog $catalog,
        private readonly PaymentProviderInterface $provider,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return list<SyncResult> */
    public function sync(bool $dryRun = false): array
    {
        $em = $this->repository->em();
        $results = [];

        if (!$dryRun) {
            foreach ($this->catalog->features() as $definition) {
                $feature = $this->repository->feature($definition->id);
                if (null === $feature) {
                    $em->persist(new Feature($definition->id, $definition->type));
                } else {
                    $feature->setType($definition->type);
                }
            }
            $em->flush();
        }

        foreach ($this->catalog->plans() as $plan) {
            $existing = $this->repository->latestProduct($plan->id);
            $action = match (true) {
                null === $existing, $existing->getHash() !== $plan->hash() => SyncResult::CREATED,
                $existing->getName() !== $plan->name, !$existing->isSynced() => SyncResult::UPDATED,
                default => SyncResult::UNCHANGED,
            };
            $version = null === $existing ? 1 : (SyncResult::CREATED === $action ? $existing->getVersion() + 1 : $existing->getVersion());
            $results[] = new SyncResult($plan->id, $action, $version);

            if ($dryRun || SyncResult::UNCHANGED === $action) {
                continue;
            }

            $product = $existing;
            if (SyncResult::CREATED === $action) {
                $product = $this->createVersion($plan, $version);
            } else {
                \assert(null !== $product);
                $product->rename($plan->name);
            }

            if (!$plan->isFree()) {
                $priceUnchanged = null !== $existing
                    && $existing->getPriceAmount() === $plan->priceAmount
                    && $existing->getPriceCurrency() === $plan->priceCurrency
                    && $existing->getPriceInterval() === $plan->priceInterval?->value;
                $ref = $this->provider->syncProduct(new ProductSyncRequest(
                    planId: $plan->id,
                    version: $version,
                    name: $plan->name,
                    priceAmount: (int) $plan->priceAmount,
                    priceCurrency: (string) $plan->priceCurrency,
                    priceInterval: (string) $plan->priceInterval?->value,
                    existingProductId: $existing?->getProviderProductId(),
                    existingPriceId: $priceUnchanged ? $existing->getProviderPriceId() : null,
                ));
                $product->linkProvider($ref->productId, $ref->priceId);
            }
            $em->flush();
        }

        return $results;
    }

    private function createVersion(PlanDefinition $plan, int $version): Product
    {
        $product = new Product(
            $plan->id,
            $version,
            $plan->name,
            $plan->group,
            $plan->isDefault,
            $plan->priceAmount,
            $plan->priceCurrency,
            $plan->priceInterval?->value,
            $plan->trialDays,
            $plan->hash(),
            $this->clock->now(),
        );
        foreach ($plan->includes as $grant) {
            $feature = $this->repository->feature($grant->featureId) ?? throw new \LogicException("Feature {$grant->featureId} was not persisted.");
            $product->addFeature($feature, $grant->limit, $grant->reset?->value);
        }
        $this->repository->em()->persist($product);

        return $product;
    }
}
