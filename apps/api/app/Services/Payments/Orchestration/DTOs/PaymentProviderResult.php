<?php

namespace App\Services\Payments\Orchestration\DTOs;

use App\Enums\PaymentTransactionStatus;

final class PaymentProviderResult
{
    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     * @param  array<string, mixed>|null  $verificationPayload
     */
    public function __construct(
        public readonly bool $ok,
        public readonly PaymentTransactionStatus $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $externalTransactionId = null,
        public readonly ?string $checkoutUrl = null,
        public readonly ?string $successIndicator = null,
        public readonly ?array $requestPayload = null,
        public readonly ?array $responsePayload = null,
        public readonly ?array $verificationPayload = null,
        public readonly ?string $message = null,
    ) {}
}
