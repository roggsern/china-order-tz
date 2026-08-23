<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Payments\Gateways\Snippe\SnippePhoneNormalizer;
use App\Services\Payments\Orchestration\PaymentOrchestrator;
use App\Services\Payments\PaymentConfigurationResolver;
use App\Support\Http\ApiResponse;
use InvalidArgumentException;

class StartPaymentTransactionAction
{
    public function __construct(
        private readonly PaymentOrchestrator $orchestrator,
        private readonly PaymentConfigurationResolver $paymentConfiguration,
    ) {}

    public function handle(
        User $user,
        Order $order,
        ?string $provider = null,
        ?string $phoneNumber = null,
    ): PaymentTransaction {
        $resolvedProvider = $this->paymentConfiguration->resolveStartProvider($provider);
        $normalizedPhone = $this->resolvePhoneForProvider($resolvedProvider, $phoneNumber);

        return $this->orchestrator->start($user, $order, $resolvedProvider, $normalizedPhone);
    }

    private function resolvePhoneForProvider(string $provider, ?string $phoneNumber): ?string
    {
        if ($provider !== PaymentMethod::Snippe->value) {
            return null;
        }

        if (! filled($phoneNumber)) {
            ApiResponse::throwCodedValidation([
                'phone_number' => ['Phone number is required for Snippe mobile money payments.'],
            ], 'payment_failed');
        }

        try {
            return SnippePhoneNormalizer::normalize((string) $phoneNumber);
        } catch (InvalidArgumentException) {
            ApiResponse::throwCodedValidation([
                'phone_number' => ['Invalid Tanzania mobile phone number.'],
            ], 'payment_failed');
        }
    }
}
