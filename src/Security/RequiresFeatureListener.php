<?php

declare(strict_types=1);

namespace PayKit\Bundle\Security;

use PayKit\Bundle\PayKit;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/** Enforces #[RequiresFeature] and reports consumption after successful responses. */
final class RequiresFeatureListener implements EventSubscriberInterface
{
    private const REQUEST_ATTR = '_paykit_consume';

    public function __construct(
        private readonly PayKit $payKit,
        private readonly CustomerResolverInterface $resolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => ['onControllerArguments', 10],
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onControllerArguments(ControllerArgumentsEvent $event): void
    {
        /** @var list<RequiresFeature> $attributes */
        $attributes = $event->getAttributes(RequiresFeature::class);
        if ([] === $attributes) {
            return;
        }
        $customer = $this->resolver->resolve($event->getRequest());
        $consume = [];
        foreach ($attributes as $attribute) {
            if (null === $customer || !$this->payKit->can($customer->id, $attribute->feature, max($attribute->required, $attribute->consume))) {
                throw new HttpException(null === $customer ? 401 : $attribute->statusCode, $attribute->message);
            }
            if ($attribute->consume > 0) {
                $consume[] = [$customer->id, $attribute->feature, $attribute->consume];
            }
        }
        if ([] !== $consume) {
            $event->getRequest()->attributes->set(self::REQUEST_ATTR, $consume);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        /** @var list<array{0: string, 1: string, 2: int}>|null $consume */
        $consume = $event->getRequest()->attributes->get(self::REQUEST_ATTR);
        if (null === $consume || !$event->getResponse()->isSuccessful()) {
            return;
        }
        $event->getRequest()->attributes->remove(self::REQUEST_ATTR);
        foreach ($consume as [$customerId, $feature, $amount]) {
            $this->payKit->report($customerId, $feature, $amount);
        }
    }
}
