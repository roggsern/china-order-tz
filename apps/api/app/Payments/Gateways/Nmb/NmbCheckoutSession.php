<?php

namespace App\Payments\Gateways\Nmb;

readonly class NmbCheckoutSession
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $success,
        public ?string $sessionId,
        public ?string $successIndicator,
        public ?string $gatewayReference,
        public ?string $checkoutUrl,
        public ?string $result,
        public array $rawResponse,
        public ?string $message = null,
    ) {}
}
