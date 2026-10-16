<?php

declare(strict_types=1);

namespace PayKit\Bundle\Service;

use PayKit\Bundle\Catalog\Catalog;
use PayKit\Bundle\Entity\CheckoutSession;
use PayKit\Bundle\Entity\Customer;
use PayKit\Bundle\Entity\Entitlement;
use PayKit\Bundle\Entity\Product;
use PayKit\Bundle\Entity\Subscription;
use PayKit\Bundle\Event\SubscriptionActivatedEvent;
use PayKit\Bundle\Event\SubscriptionEndedEvent;
use PayKit\Bundle\Event\SubscriptionScheduledEvent;
use PayKit\Bundle\Exception\PayKitException;
use PayKit\Bundle\Model\SubscribeAction;
use PayKit\Bundle\Model\SubscribeResult;
use PayKit\Bundle\Provider\Dto\CheckoutRequest;
use PayKit\Bundle\Provider\Dto\ProviderSubscription;
use PayKit\Bundle\Provider\PaymentProviderInterface;
use PayKit\Bundle\Repository\BillingRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Subscription lifecycle. A single subscribe() call covers new subscriptions, upgrades
 * (immediate), downgrades and cancel-to-free (scheduled at period end), resumption and no-ops.
 * Plans in the same group are mutually exclusive.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly BillingRepository $repository,
        private readonly Catalog $catalog,
        private readonly PaymentProviderInterface $provider,
        private readonly ProviderCustomerLinker $linker,
        private readonly EventQueue $events,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function subscribe(string $customerId, string $planId, ?string $successUrl = null, ?string $cancelUrl = null, bool $forceCheckout = false): SubscribeResult
    {
        $this->catalog->plan($planId);
        $customer = $this->requireCustomer($customerId);
        $target = $this->requireSyncedProduct($planId);
        $now = $this->clock->now();
        $active = $this->repository->activeSubscriptionInGroup($customerId, $target->getGroup(), $now);

        $result = match (true) {
            null !== $active && $active->getPlanId() === $planId => $this->handleSamePlan($active),
            null === $active || !$active->hasProviderSubscription() => $target->isFree()
                ? $this->activateNow($customer, $target)
                : $this->startPaid($customer, $target, $successUrl, $cancelUrl, $forceCheckout),
            $target->isFree() => $this->scheduleCancelToFree($active, $target),
            $active->getProduct()->getPriceCurrency() !== $target->getPriceCurrency() => $this->checkout($customer, $target, $successUrl, $cancelUrl),
            (int) $target->getPriceAmount() <= (int) $active->getProduct()->getPriceAmount() => $this->scheduleDowngrade($active, $target),
            default => $this->upgrade($customer, $active, $target, $successUrl, $cancelUrl, $forceCheckout),
        };

        $this->events->flush();
        $this->logger->info('PayKit subscribe {customer} -> {plan}: {action}', ['customer' => $customerId, 'plan' => $planId, 'action' => $result->action->value]);

        return $result;
    }

    /** Puts a new customer on each group's default (free) plan. Caller flushes. */
    public function activateDefaultPlans(Customer $customer): void
    {
        foreach ($this->catalog->defaultPlans() as $group => $plan) {
            if (null !== $this->repository->activeSubscriptionInGroup($customer->getId(), (string) $group, $this->clock->now())) {
                continue;
            }
            $product = $this->repository->latestProduct($plan->id);
            if (null === $product) {
                $this->logger->warning('Default plan "{plan}" is not pushed yet; run paykit:push.', ['plan' => $plan->id]);
                continue;
            }
            $this->activate($customer, $product);
        }
    }

    /**
     * Applies a completed hosted checkout. Returns the affected customer id, or null when the
     * session is unknown or was already applied.
     */
    public function completeCheckout(string $sessionId, ?ProviderSubscription $providerSub): ?string
    {
        $session = $this->repository->checkoutSession($sessionId);
        if (null === $session || $session->isCompleted() || null === $providerSub) {
            return null;
        }
        $customer = $this->requireCustomer($session->getCustomerId());
        $product = $this->repository->productByInternalId($session->getProductInternalId()) ?? throw PayKitException::planNotFound($session->getProductInternalId());
        $session->complete($this->clock->now());

        if (null === $this->repository->subscriptionByProviderId($providerSub->id)) {
            $previous = $this->repository->activeSubscriptionInGroup($customer->getId(), $product->getGroup(), $this->clock->now());
            if (null !== $previous && $previous->hasProviderSubscription() && $previous->getProviderSubscriptionId() !== $providerSub->id) {
                // Upgrade / currency change through checkout: the old paid subscription stops now.
                $this->provider->cancelSubscription((string) $previous->getProviderSubscriptionId(), false, $previous->getProviderScheduleId());
            }
            $this->activate($customer, $product, $providerSub);
        }

        return $customer->getId();
    }

    /** Mirrors a provider subscription update into local state. Returns the affected customer id. */
    public function syncFromProvider(ProviderSubscription $ps, ?string $providerCustomerId): ?string
    {
        $now = $this->clock->now();
        $local = $this->repository->subscriptionByProviderId($ps->id);
        $isActive = \in_array($ps->status, Subscription::ACTIVE_STATUSES, true);

        if (null === $local) {
            // Subscription we have not seen yet (e.g. update arrived before checkout.completed).
            $customer = null !== $providerCustomerId ? $this->repository->customerByProviderId($providerCustomerId) : null;
            $product = null !== $ps->priceId ? $this->repository->productByProviderPrice($ps->priceId) : null;
            if (null === $customer || null === $product || !$isActive) {
                return $customer?->getId();
            }
            $this->activate($customer, $product, $ps);

            return $customer->getId();
        }

        $customerId = $local->getCustomer()->getId();

        if (\in_array($ps->status, ['canceled', 'incomplete_expired'], true) || (null !== $ps->endedAt && $ps->endedAt <= $now)) {
            $this->handleProviderEnded($local);

            return $customerId;
        }

        if (null !== $ps->priceId && $ps->priceId !== $local->getProduct()->getProviderPriceId() && $isActive) {
            // Price changed on the provider side: scheduled downgrade took effect, a pending upgrade
            // got paid, or the plan was changed in the customer portal.
            $product = $this->repository->productByProviderPrice($ps->priceId);
            if (null !== $product) {
                $this->activate($local->getCustomer(), $product, $ps);

                return $customerId;
            }
        }

        $local->syncFromProvider($ps->id, $ps->status, $ps->cancelAtPeriodEnd, $ps->currentPeriodStart, $ps->currentPeriodEnd, $ps->canceledAt, $ps->trialEnd, $ps->scheduleId ?? $local->getProviderScheduleId(), $now);
        if (!$ps->cancelAtPeriodEnd && null === $ps->scheduleId && null !== $local->getScheduledPlanId()) {
            $local->clearSchedule($now);
        } elseif ($ps->cancelAtPeriodEnd && null === $local->getScheduledPlanId()) {
            // Canceled outside PayKit (e.g. customer portal): fall back to the group's default plan.
            $local->schedule($this->catalog->defaultPlanForGroup($local->getGroup())?->id, true, $now);
        }
        $this->events->touchCustomer($customerId);

        return $customerId;
    }

    public function handleProviderDeleted(string $providerSubscriptionId): ?string
    {
        $local = $this->repository->subscriptionByProviderId($providerSubscriptionId);
        if (null === $local) {
            return null;
        }
        $this->handleProviderEnded($local);

        return $local->getCustomer()->getId();
    }

    /** Ends every subscription of a customer, canceling provider subscriptions immediately. Caller flushes. */
    public function endAll(Customer $customer): void
    {
        foreach ($this->repository->currentSubscriptions($customer->getId()) as $sub) {
            if ($sub->hasProviderSubscription()) {
                $this->provider->cancelSubscription((string) $sub->getProviderSubscriptionId(), false, $sub->getProviderScheduleId());
            }
            $this->end($sub, 'canceled');
        }
    }

    private function handleSamePlan(Subscription $active): SubscribeResult
    {
        if (null === $active->getScheduledPlanId() && !$active->isCancelAtPeriodEnd()) {
            return new SubscribeResult(SubscribeAction::Noop);
        }
        if ($active->hasProviderSubscription()) {
            $ps = $this->provider->resumeSubscription((string) $active->getProviderSubscriptionId(), $active->getProviderScheduleId());
            $active->syncFromProvider($ps->id, $ps->status, false, $ps->currentPeriodStart, $ps->currentPeriodEnd, null, $ps->trialEnd, null, $this->clock->now());
        }
        $active->clearSchedule($this->clock->now());
        $this->events->push(new SubscriptionScheduledEvent($active, null));
        $this->events->touchCustomer($active->getCustomer()->getId());

        return new SubscribeResult(SubscribeAction::Resumed);
    }

    private function activateNow(Customer $customer, Product $target): SubscribeResult
    {
        $this->activate($customer, $target);

        return new SubscribeResult(SubscribeAction::Activated);
    }

    private function startPaid(Customer $customer, Product $target, ?string $successUrl, ?string $cancelUrl, bool $forceCheckout): SubscribeResult
    {
        $providerCustomerId = $this->linker->ensure($customer);
        if ($forceCheckout || !$this->repository->hasDefaultPaymentMethod($customer->getId())) {
            return $this->checkout($customer, $target, $successUrl, $cancelUrl);
        }
        $ps = $this->provider->createSubscription($providerCustomerId, (string) $target->getProviderPriceId(), $target->getTrialDays());
        if (!\in_array($ps->status, Subscription::ACTIVE_STATUSES, true)) {
            // e.g. 3DS required: activation happens on the subscription.updated webhook.
            return new SubscribeResult(SubscribeAction::Checkout, null, $ps->latestInvoice, $ps->paymentActionUrl);
        }
        $this->activate($customer, $target, $ps);

        return new SubscribeResult(SubscribeAction::Activated, null, $ps->latestInvoice);
    }

    private function checkout(Customer $customer, Product $target, ?string $successUrl, ?string $cancelUrl): SubscribeResult
    {
        if (null === $successUrl) {
            throw new \InvalidArgumentException(\sprintf('A successUrl is required to check out plan "%s".', $target->getPlanId()));
        }
        $providerCustomerId = $this->linker->ensure($customer);
        $result = $this->provider->createCheckout(new CheckoutRequest(
            providerCustomerId: $providerCustomerId,
            priceId: (string) $target->getProviderPriceId(),
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
            metadata: ['paykit_customer_id' => $customer->getId(), 'paykit_plan_id' => $target->getPlanId()],
            trialDays: $target->getTrialDays(),
        ));
        $this->repository->em()->persist(new CheckoutSession($result->sessionId, $customer->getId(), $target->getInternalId(), $this->clock->now()));

        return new SubscribeResult(SubscribeAction::Checkout, $result->url);
    }

    private function upgrade(Customer $customer, Subscription $active, Product $target, ?string $successUrl, ?string $cancelUrl, bool $forceCheckout): SubscribeResult
    {
        if ($forceCheckout || !$this->repository->hasDefaultPaymentMethod($customer->getId())) {
            return $this->checkout($customer, $target, $successUrl, $cancelUrl);
        }
        $ps = $this->provider->updateSubscription((string) $active->getProviderSubscriptionId(), (string) $target->getProviderPriceId());
        if (!\in_array($ps->status, Subscription::ACTIVE_STATUSES, true)) {
            return new SubscribeResult(SubscribeAction::Checkout, null, $ps->latestInvoice, $ps->paymentActionUrl);
        }
        $this->activate($customer, $target, $ps);

        return new SubscribeResult(SubscribeAction::Upgraded, null, $ps->latestInvoice);
    }

    private function scheduleDowngrade(Subscription $active, Product $target): SubscribeResult
    {
        $ps = $this->provider->scheduleSubscriptionChange((string) $active->getProviderSubscriptionId(), (string) $target->getProviderPriceId(), $active->getProviderScheduleId());
        $active->schedule($target->getPlanId(), false, $this->clock->now(), $ps->scheduleId);
        $this->events->push(new SubscriptionScheduledEvent($active, $target->getPlanId()));
        $this->events->touchCustomer($active->getCustomer()->getId());

        return new SubscribeResult(SubscribeAction::Scheduled);
    }

    private function scheduleCancelToFree(Subscription $active, Product $target): SubscribeResult
    {
        $this->provider->cancelSubscription((string) $active->getProviderSubscriptionId(), true, $active->getProviderScheduleId());
        $active->schedule($target->getPlanId(), true, $this->clock->now());
        $this->events->push(new SubscriptionScheduledEvent($active, $target->getPlanId()));
        $this->events->touchCustomer($active->getCustomer()->getId());

        return new SubscribeResult(SubscribeAction::Scheduled);
    }

    private function handleProviderEnded(Subscription $local): void
    {
        $this->end($local, 'canceled');
        $targetPlanId = $local->getScheduledPlanId() ?? $this->catalog->defaultPlanForGroup($local->getGroup())?->id;
        if (null === $targetPlanId) {
            return;
        }
        $product = $this->repository->latestProduct($targetPlanId);
        if (null !== $product && $product->isFree()) {
            $this->activate($local->getCustomer(), $product);
        }
    }

    /**
     * Makes $product the customer's active plan in its group, ending the previous one and
     * provisioning fresh entitlements.
     */
    private function activate(Customer $customer, Product $product, ?ProviderSubscription $ps = null): Subscription
    {
        $now = $this->clock->now();
        $previous = $this->repository->activeSubscriptionInGroup($customer->getId(), $product->getGroup(), $now);
        if (null !== $previous) {
            $this->end($previous);
        }

        $sub = new Subscription($customer, $product, null !== $ps ? $ps->status : 'active', $now);
        if (null !== $ps) {
            $sub->syncFromProvider($ps->id, $ps->status, $ps->cancelAtPeriodEnd, $ps->currentPeriodStart, $ps->currentPeriodEnd, $ps->canceledAt, $ps->trialEnd, $ps->scheduleId, $now);
        }
        $em = $this->repository->em();
        $em->persist($sub);
        foreach ($product->getFeatures() as $pf) {
            $em->persist(new Entitlement($sub, $pf->getFeature()->getId(), $pf->getLimit(), $pf->getResetInterval(), $sub->getCurrentPeriodStartAt() ?? $now));
        }
        // Make the new subscription visible to subsequent queries in this unit of work.
        $em->flush();

        $this->events->push(new SubscriptionActivatedEvent($sub, $previous));
        $this->events->touchCustomer($customer->getId());

        return $sub;
    }

    private function end(Subscription $sub, string $status = 'ended'): void
    {
        $sub->end($this->clock->now(), $status);
        $this->repository->em()->flush();
        $this->events->push(new SubscriptionEndedEvent($sub));
        $this->events->touchCustomer($sub->getCustomer()->getId());
    }

    private function requireCustomer(string $customerId): Customer
    {
        $customer = $this->repository->customer($customerId);
        if (null === $customer || $customer->isDeleted()) {
            throw PayKitException::customerNotFound($customerId);
        }

        return $customer;
    }

    private function requireSyncedProduct(string $planId): Product
    {
        $product = $this->repository->latestProduct($planId);
        if (null === $product || !$product->isSynced()) {
            throw PayKitException::planNotSynced($planId);
        }
        if ($product->getHash() !== $this->catalog->plan($planId)->hash()) {
            $this->logger->warning('Plan "{plan}" configuration differs from the pushed version {version}; run paykit:push.', ['plan' => $planId, 'version' => $product->getVersion()]);
        }

        return $product;
    }
}
