<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'status' => PaymentStatus::Pending,
            'amount' => fake()->randomFloat(2, 10000, 500000),
            'currency' => 'TZS',
            'transaction_id' => fake()->optional()->uuid(),
            'reference' => fake()->optional()->bothify('REF-########'),
            'paid_at' => null,
            'metadata' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Completed,
            'transaction_id' => fake()->uuid(),
            'paid_at' => now(),
        ]);
    }

    public function nmb(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => PaymentMethod::Nmb,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Processing,
        ]);
    }
}
