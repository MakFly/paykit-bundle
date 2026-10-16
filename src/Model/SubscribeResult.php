<?php

declare(strict_types=1);

namespace PayKit\Bundle\Model;

use PayKit\Bundle\Provider\Dto\ProviderInvoice;

final readonly class SubscribeResult
{
    public function __construct(
        public SubscribeAction $action,
        public ?string $paymentUrl = null,
        public ?ProviderInvoice $invoice = null,
        /** URL where the customer completes extra authentication (3DS), when required. */
        public ?string $requiredActionUrl = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'action' => $this->action->value,
            'paymentUrl' => $this->paymentUrl,
            'invoice' => null === $this->invoice ? null : [
                'providerInvoiceId' => $this->invoice->id,
                'status' => $this->invoice->status,
                'totalAmount' => $this->invoice->total,
                'currency' => $this->invoice->currency,
                'hostedUrl' => $this->invoice->hostedUrl,
            ],
            'requiredAction' => null === $this->requiredActionUrl ? null : ['url' => $this->requiredActionUrl],
        ];
    }
}
