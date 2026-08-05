<?php

namespace App\Payments\Gateways\Nmb;

readonly class NmbPaymentOutcomeResult
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public NmbPaymentOutcome $outcome,
        public string $message,
        public array $context = [],
    ) {}
}
