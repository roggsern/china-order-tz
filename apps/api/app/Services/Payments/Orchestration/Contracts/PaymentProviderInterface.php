<?php

namespace App\Services\Payments\Orchestration\Contracts;

use App\Models\PaymentTransaction;
use App\Services\Payments\Orchestration\DTOs\PaymentInitiationRequest;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;

interface PaymentProviderInterface
{
    public function key(): string;

    public function initiate(PaymentInitiationRequest $request): PaymentProviderResult;

    public function refresh(PaymentTransaction $transaction): PaymentProviderResult;

    public function verify(PaymentTransaction $transaction): PaymentProviderResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(PaymentTransaction $transaction, array $payload): PaymentProviderResult;
}
