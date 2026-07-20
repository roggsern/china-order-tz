<?php

namespace Database\Factories;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => null,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ];
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'session_id' => Str::uuid()->toString(),
        ]);
    }

    public function checkoutSession(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CartStatus::CheckoutSession,
        ]);
    }
}
