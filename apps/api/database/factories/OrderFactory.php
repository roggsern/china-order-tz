<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 20000, 500000);
        $discount = fake()->randomFloat(2, 0, $subtotal * 0.2);
        $tax = round(($subtotal - $discount) * 0.18, 2);
        $shipping = fake()->randomFloat(2, 0, 15000);
        $total = $subtotal - $discount + $tax + $shipping;

        return [
            'user_id' => User::factory(),
            'coupon_id' => null,
            'order_number' => 'COT-'.fake()->unique()->numerify('######'),
            'status' => fake()->randomElement(OrderStatus::cases()),
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'shipping_amount' => $shipping,
            'total' => $total,
            'currency' => 'TZS',
            'is_demo' => false,
            'notes' => fake()->optional()->sentence(),
            'placed_at' => now(),
        ];
    }

    public function withCoupon(): static
    {
        return $this->state(fn (array $attributes) => [
            'coupon_id' => Coupon::factory(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Pending,
        ]);
    }
}
