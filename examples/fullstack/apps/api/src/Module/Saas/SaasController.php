<?php

declare(strict_types=1);

namespace App\Module\Saas;

use App\Module\Shared\Billing;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/api/saas', format: 'json')]
final class SaasController
{
    public function __construct(private readonly Billing $billing)
    {
    }

    #[Route('/bootstrap', methods: ['POST'])]
    public function bootstrap(): JsonResponse
    {
        $this->billing->ensureCustomer(SaasModule::CUSTOMER_ID, 'saas@example.test', 'Équipe SaaS de démonstration');

        return new JsonResponse($this->snapshot());
    }

    #[Route('', methods: ['GET'])]
    public function state(): JsonResponse
    {
        return new JsonResponse($this->snapshot());
    }

    #[Route('/subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $result = $this->billing->subscribe(SaasModule::CUSTOMER_ID, SaasModule::GROUP, $request->toArray()['planId'] ?? null);

        return new JsonResponse(['result' => $result->toArray(), ...$this->snapshot()]);
    }

    /**
     * One generation costs one message. The Expert model needs the pro_models
     * feature; it is checked first so a refused request consumes nothing.
     */
    #[Route('/chat', methods: ['POST'])]
    public function chat(Request $request, Writer $writer): JsonResponse
    {
        $payload = $request->toArray();
        $prompt = $payload['prompt'] ?? null;
        $model = $payload['model'] ?? 'standard';
        if (!is_string($prompt) || '' === trim($prompt) || mb_strlen($prompt) > 2000) {
            throw new BadRequestHttpException('Le texte doit contenir entre 1 et 2000 caractères.');
        }
        if (!in_array($model, ['standard', 'expert'], true)) {
            throw new BadRequestHttpException('Le modèle doit être "standard" ou "expert".');
        }
        $payKit = $this->billing->payKit();
        if ('expert' === $model && !$payKit->can(SaasModule::CUSTOMER_ID, 'pro_models')) {
            return new JsonResponse(['result' => [
                'reason' => 'model',
                'message' => 'Le modèle Expert est inclus à partir du forfait Pro.',
            ], ...$this->snapshot()], 402);
        }
        $report = $payKit->report(SaasModule::CUSTOMER_ID, 'messages');
        if (!$report->success) {
            return new JsonResponse(['result' => [
                'reason' => 'quota',
                'message' => 'Tu as utilisé tous tes messages du mois.',
            ], ...$this->snapshot()], 402);
        }

        return new JsonResponse(['result' => [
            'reply' => ['model' => $model, 'text' => $writer->rewrite($prompt, $model)],
            'balance' => $report->balance?->toArray(),
        ], ...$this->snapshot()]);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'customer' => $this->billing->customer(SaasModule::CUSTOMER_ID, SaasModule::GROUP, SaasModule::FEATURES),
            'plans' => $this->billing->plans(SaasModule::GROUP),
            'proAllowed' => $this->billing->payKit()->can(SaasModule::CUSTOMER_ID, 'pro_models'),
        ];
    }
}
