<?php

declare(strict_types=1);

namespace App\Module\Shared;

use Doctrine\ORM\EntityManagerInterface;
use PayKit\Bundle\Catalog\FeatureGrant;
use PayKit\Bundle\Catalog\PlanDefinition;
use PayKit\Bundle\Entity\PaymentMethod;
use PayKit\Bundle\Model\SubscribeResult;
use PayKit\Bundle\PayKit;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PayKit calls shared by every module. A module only sees its own plan group:
 * PayKit attaches every group's default plan to a new customer.
 */
final class Billing
{
    public function __construct(private readonly PayKit $payKit, private readonly EntityManagerInterface $em)
    {
    }

    public function payKit(): PayKit
    {
        return $this->payKit;
    }

    public function ensureCustomer(string $customerId, string $email, string $name): void
    {
        if (null !== $this->payKit->getCustomer($customerId)) {
            return;
        }
        $customer = $this->payKit->upsertCustomer($customerId, $email, $name);
        // A deleted customer is soft-deleted by PayKit and keeps its card: reuse it.
        if (null !== $this->em->getRepository(PaymentMethod::class)->findOneBy(['providerMethodId' => 'pm_'.$customerId])) {
            return;
        }
        // A fictional saved card allows the real subscription service to activate paid plans.
        $card = new PaymentMethod($customer, 'pm_'.$customerId, 'card');
        $card->update('card', 'visa', '4242', 12, 2099, true);
        $this->em->persist($card);
        $this->em->flush();
    }

    public function subscribe(string $customerId, string $group, mixed $planId): SubscribeResult
    {
        $catalog = $this->payKit->catalog();
        if (!is_string($planId) || !$catalog->hasPlan($planId) || $catalog->plan($planId)->group !== $group) {
            throw new BadRequestHttpException('Choisis un forfait du catalogue de ce module.');
        }

        return $this->payKit->subscribe($customerId, $planId);
    }

    /** @return list<array<string, mixed>> */
    public function plans(string $group): array
    {
        $plans = array_filter($this->payKit->catalog()->plans(), static fn (PlanDefinition $plan): bool => $plan->group === $group);

        return array_values(array_map(static fn (PlanDefinition $plan): array => [
            'id' => $plan->id,
            'name' => $plan->name,
            'amount' => $plan->priceAmount ?? 0,
            'currency' => $plan->priceCurrency ?? 'eur',
            'interval' => $plan->priceInterval?->value ?? 'month',
            // null limit: unlimited metered grant or boolean feature.
            'includes' => array_combine(
                array_map(static fn (FeatureGrant $grant): string => $grant->featureId, $plan->includes),
                array_map(static fn (FeatureGrant $grant): ?int => $grant->limit, $plan->includes),
            ),
        ], $plans));
    }

    /**
     * @param list<string> $features
     *
     * @return array<string, mixed>
     */
    public function customer(string $customerId, string $group, array $features): array
    {
        $view = $this->payKit->getCustomer($customerId)
            ?? throw new NotFoundHttpException('Initialise le compte avec POST /bootstrap.');
        $customer = $view->toArray();
        $subscriptions = [];
        foreach ($view->subscriptions as $index => $subscription) {
            if ($subscription->getGroup() === $group) {
                $subscriptions[] = $customer['subscriptions'][$index];
            }
        }
        $customer['subscriptions'] = $subscriptions;
        $customer['entitlements'] = array_intersect_key($customer['entitlements'], array_flip($features));

        return $customer;
    }
}
