<?php

namespace App\Payments\DTOs;

class InitiatePaymentResult
{
    /**
     * @param  array<string, mixed>|null  $gatewayResponse
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $checkoutRequestId = null,
        public readonly ?string $gatewayReference = null,
        public readonly ?string $gatewaySessionId = null,
        public readonly ?string $successIndicator = null,
        public readonly ?string $checkoutUrl = null,
        public readonly ?array $gatewayResponse = null,
    ) {}
}
