<?php

declare(strict_types=1);

namespace PayKit\Bundle\Controller;

use PayKit\Bundle\Exception\PayKitException;
use PayKit\Bundle\PayKit;
use PayKit\Bundle\Security\CustomerResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Client-facing JSON API (PayKit's client SDK endpoints). The customer always comes from the
 * CustomerResolver; a customerId in the body must match it.
 */
final class ApiController
{
    /** @param list<string> $trustedOrigins */
    public function __construct(
        private readonly PayKit $payKit,
        private readonly CustomerResolverInterface $resolver,
        private readonly array $trustedOrigins = [],
    ) {
    }

    public function subscribe(Request $request): JsonResponse
    {
        return $this->handle($request, function (string $customerId, array $body, Request $request): array {
            $planId = $body['planId'] ?? null;
            if (!\is_string($planId)) {
                throw new \InvalidArgumentException('planId is required.');
            }

            return $this->payKit->subscribe(
                $customerId,
                $planId,
                $this->absolute($request, $body['successUrl'] ?? null),
                $this->absolute($request, $body['cancelUrl'] ?? null),
                (bool) ($body['forceCheckout'] ?? false),
            )->toArray();
        });
    }

    public function customerPortal(Request $request): JsonResponse
    {
        return $this->handle($request, fn (string $customerId, array $body, Request $request): array => [
            'url' => $this->payKit->customerPortal($customerId, (string) $this->absolute($request, $body['returnUrl'] ?? '/')),
        ]);
    }

    public function customer(Request $request): JsonResponse
    {
        return $this->handle($request, fn (string $customerId): array => $this->payKit->getCustomer($customerId)?->toArray() ?? []);
    }

    /** @param callable(string, array<string, mixed>, Request): array<string, mixed> $action */
    private function handle(Request $request, callable $action): JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if (null !== $origin && 'GET' !== $request->getMethod() && $origin !== $request->getSchemeAndHttpHost() && !\in_array($origin, $this->trustedOrigins, true)) {
            return new JsonResponse(['error' => 'Untrusted origin. Add it to paykit.trusted_origins.'], 403);
        }
        $customer = $this->resolver->resolve($request);
        if (null === $customer) {
            return new JsonResponse(['error' => 'Unauthenticated.'], 401);
        }
        try {
            $body = '' === $request->getContent() ? [] : $request->toArray();
            if (isset($body['customerId']) && $body['customerId'] !== $customer->id) {
                return new JsonResponse(['error' => 'customerId does not match the authenticated customer.'], 403);
            }
            if (null === $this->payKit->getCustomer($customer->id)) {
                $this->payKit->upsertCustomer($customer->id, $customer->email, $customer->name);
            }

            return new JsonResponse($action($customer->id, $body, $request));
        } catch (PayKitException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode], PayKitException::PLAN_NOT_FOUND === $e->errorCode ? 404 : 422);
        } catch (\InvalidArgumentException|\Symfony\Component\HttpFoundation\Exception\JsonException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function absolute(Request $request, mixed $url): ?string
    {
        if (!\is_string($url) || '' === $url) {
            return null;
        }
        if (str_starts_with($url, '/')) {
            $origin = $request->headers->get('Origin');
            $base = (null !== $origin && \in_array($origin, $this->trustedOrigins, true)) ? $origin : $request->getSchemeAndHttpHost();

            return $base.$url;
        }

        return $url;
    }
}
