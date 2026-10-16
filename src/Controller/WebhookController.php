<?php

declare(strict_types=1);

namespace PayKit\Bundle\Controller;

use PayKit\Bundle\Exception\InvalidWebhookException;
use PayKit\Bundle\PayKit;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** POST {prefix}/webhook — point your Stripe webhook endpoint here. */
final class WebhookController
{
    public function __construct(private readonly PayKit $payKit)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $headers = array_map(static fn (array $values): string => (string) ($values[0] ?? ''), $request->headers->all());
        try {
            $stats = $this->payKit->handleWebhook($request->getContent(), $headers);
        } catch (InvalidWebhookException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse(['received' => true] + $stats);
    }
}
