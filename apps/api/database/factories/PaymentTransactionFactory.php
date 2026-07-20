<?php

namespace Database\Factories;

use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => PaymentProvider::Nmb,
            'provider_reference' => null,
            'merchant_reference' => 'COTZ-PAY-'.Str::upper(Str::random(10)),
            'currency' => 'TZS',
            'amount' => 10000,
            'status' => PaymentTransactionStatus::Pending,
            'request_payload' => null,
            'response_payload' => null,
            'initiated_at' => null,
            'completed_at' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => PaymentTransactionStatus::Processing,
            'initiated_at' => now(),
            'provider_reference' => 'NMB-PLACEHOLDER-'.Str::upper(Str::random(8)),
        ]);
    }
}
