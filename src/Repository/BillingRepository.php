<?php

declare(strict_types=1);

namespace PayKit\Bundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PayKit\Bundle\Entity\CheckoutSession;
use PayKit\Bundle\Entity\Customer;
use PayKit\Bundle\Entity\Entitlement;
use PayKit\Bundle\Entity\Feature;
use PayKit\Bundle\Entity\Invoice;
use PayKit\Bundle\Entity\PaymentMethod;
use PayKit\Bundle\Entity\Product;
use PayKit\Bundle\Entity\Subscription;

/**
 * All PayKit persistence queries in one place (the bundle's storage adapter).
 */
class BillingRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function em(): EntityManagerInterface
    {
        return $this->em;
    }

    public function customer(string $id): ?Customer
    {
        return $this->em->find(Customer::class, $id);
    }

    public function customerByProviderId(string $providerCustomerId): ?Customer
    {
        return $this->em->getRepository(Customer::class)->findOneBy(['providerCustomerId' => $providerCustomerId]);
    }

    public function feature(string $id): ?Feature
    {
        return $this->em->find(Feature::class, $id);
    }

    public function latestProduct(string $planId): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneBy(['planId' => $planId], ['version' => 'DESC']);
    }

    public function productByInternalId(string $internalId): ?Product
    {
        return $this->em->find(Product::class, $internalId);
    }

    public function productByProviderPrice(string $priceId): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneBy(['providerPriceId' => $priceId], ['version' => 'DESC']);
    }

    /** @return list<Subscription> */
    public function activeSubscriptions(string $customerId, \DateTimeImmutable $now): array
    {
        /** @var list<Subscription> */
        return $this->em->createQueryBuilder()
            ->select('s', 'p')
            ->from(Subscription::class, 's')
            ->join('s.product', 'p')
            ->where('IDENTITY(s.customer) = :customer')
            ->andWhere('s.status IN (:statuses)')
            ->andWhere('s.endedAt IS NULL OR s.endedAt > :now')
            ->setParameter('customer', $customerId)
            ->setParameter('statuses', Subscription::ACTIVE_STATUSES)
            ->setParameter('now', $now)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Non-ended subscriptions (including past_due / incomplete ones).
     *
     * @return list<Subscription>
     */
    public function currentSubscriptions(string $customerId): array
    {
        /** @var list<Subscription> */
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(Subscription::class, 's')
            ->where('IDENTITY(s.customer) = :customer')
            ->andWhere('s.endedAt IS NULL')
            ->setParameter('customer', $customerId)
            ->getQuery()->getResult();
    }

    public function activeSubscriptionInGroup(string $customerId, string $group, \DateTimeImmutable $now): ?Subscription
    {
        foreach ($this->activeSubscriptions($customerId, $now) as $sub) {
            if ($sub->getGroup() === $group) {
                return $sub;
            }
        }

        return null;
    }

    public function subscriptionByProviderId(string $providerSubscriptionId): ?Subscription
    {
        return $this->em->getRepository(Subscription::class)->findOneBy(['providerSubscriptionId' => $providerSubscriptionId, 'endedAt' => null], ['createdAt' => 'DESC']);
    }

    /** @return list<Entitlement> */
    public function activeEntitlements(string $customerId, ?string $featureId, \DateTimeImmutable $now): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e', 's')
            ->from(Entitlement::class, 'e')
            ->join('e.subscription', 's')
            ->where('e.customerId = :customer')
            ->andWhere('s.status IN (:statuses)')
            ->andWhere('s.endedAt IS NULL OR s.endedAt > :now')
            ->setParameter('customer', $customerId)
            ->setParameter('statuses', Subscription::ACTIVE_STATUSES)
            ->setParameter('now', $now)
            ->orderBy('e.nextResetAt', 'ASC');
        if (null !== $featureId) {
            $qb->andWhere('e.featureId = :feature')->setParameter('feature', $featureId);
        }

        /** @var list<Entitlement> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Atomically deducts $amount when the row still has enough balance (safe under concurrency).
     */
    public function deductEntitlement(string $entitlementId, int $amount, \DateTimeImmutable $now): bool
    {
        $affected = $this->em->createQuery(
            'UPDATE '.Entitlement::class.' e SET e.balance = e.balance - :amount, e.updatedAt = :now WHERE e.id = :id AND e.balance >= :amount'
        )
            ->setParameter('amount', $amount)
            ->setParameter('now', $now)
            ->setParameter('id', $entitlementId)
            ->execute();

        return 1 === $affected;
    }

    public function hasDefaultPaymentMethod(string $customerId): bool
    {
        return null !== $this->em->getRepository(PaymentMethod::class)->findOneBy(['customer' => $customerId, 'isDefault' => true, 'deletedAt' => null]);
    }

    public function paymentMethodByProviderId(string $providerMethodId): ?PaymentMethod
    {
        return $this->em->getRepository(PaymentMethod::class)->findOneBy(['providerMethodId' => $providerMethodId]);
    }

    public function invoiceByProviderId(string $providerInvoiceId): ?Invoice
    {
        return $this->em->getRepository(Invoice::class)->findOneBy(['providerInvoiceId' => $providerInvoiceId]);
    }

    public function checkoutSession(string $providerSessionId): ?CheckoutSession
    {
        return $this->em->getRepository(CheckoutSession::class)->findOneBy(['providerSessionId' => $providerSessionId]);
    }

    /**
     * @param list<string> $planIds
     *
     * @return array{data: list<Customer>, total: int, hasMore: bool}
     */
    public function listCustomers(int $limit, int $offset, array $planIds, \DateTimeImmutable $now): array
    {
        $qb = $this->em->createQueryBuilder()->select('c')->from(Customer::class, 'c')->where('c.deletedAt IS NULL');
        if ([] !== $planIds) {
            $qb->andWhere('EXISTS (SELECT s.id FROM '.Subscription::class.' s JOIN s.product p WHERE s.customer = c AND p.planId IN (:plans) AND s.status IN (:statuses) AND (s.endedAt IS NULL OR s.endedAt > :now))')
                ->setParameter('plans', $planIds)
                ->setParameter('statuses', Subscription::ACTIVE_STATUSES)
                ->setParameter('now', $now);
        }
        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        /** @var list<Customer> $data */
        $data = $qb->orderBy('c.createdAt', 'ASC')->addOrderBy('c.id', 'ASC')->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        return ['data' => $data, 'total' => $total, 'hasMore' => $offset + \count($data) < $total];
    }
}
