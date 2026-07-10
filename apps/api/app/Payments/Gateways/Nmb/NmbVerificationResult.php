<?php

namespace App\Payments\Gateways\Nmb;

readonly class NmbVerificationResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $verified,
        public string $message,
        public ?string $result = null,
        public ?string $orderId = null,
        public ?string $transactionId = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public array $rawResponse = [],
    ) {}
}
