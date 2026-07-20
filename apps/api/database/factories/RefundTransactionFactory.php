<?php

namespace Database\Factories;

use App\Enums\RefundTransactionStatus;
use App\Models\Order;
use App\Models\RefundTransaction;
use App\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefundTransaction>
 */
class RefundTransactionFactory extends Factory
{
    protected $model = RefundTransaction::class;

    public function definition(): array
    {
        return [
            'return_request_id' => ReturnRequest::factory(),
            'order_id' => Order::factory(),
            'amount' => fake()->randomFloat(2, 10000, 200000),
            'currency' => 'TZS',
            'status' => RefundTransactionStatus::Pending,
            'method' => 'manual',
            'reference' => null,
            'notes' => null,
        ];
    }
}
