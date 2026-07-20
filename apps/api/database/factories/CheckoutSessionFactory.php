<?php

namespace Database\Factories;

use App\Enums\CheckoutSessionStatus;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckoutSession>
 */
class CheckoutSessionFactory extends Factory
{
    protected $model = CheckoutSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cart_id' => Cart::factory(),
            'currency' => 'TZS',
            'subtotal' => '0.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'grand_total' => '0.00',
            'status' => CheckoutSessionStatus::Draft,
            'expires_at' => now()->addMinutes(30),
        ];
    }

    public function validated(): static
    {
        return $this->state(fn () => [
            'status' => CheckoutSessionStatus::Validated,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => CheckoutSessionStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
