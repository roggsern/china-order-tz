<?php

namespace Database\Factories;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryType;
use App\Models\DeliveryOption;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryOption>
 */
class DeliveryOptionFactory extends Factory
{
    protected $model = DeliveryOption::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'delivery_type' => DeliveryType::SelfPickup,
            'shipping_method' => null,
            'delivery_status' => DeliveryOptionStatus::Pending,
            'agent_name' => null,
            'agent_contact' => null,
            'notes' => null,
            'confirmed_at' => null,
        ];
    }

    public function companyShippingAir(): static
    {
        return $this->state(fn () => [
            'delivery_type' => DeliveryType::CompanyShipping,
            'shipping_method' => 'air',
        ]);
    }

    public function customerAgent(): static
    {
        return $this->state(fn () => [
            'delivery_type' => DeliveryType::CustomerAgent,
            'shipping_method' => null,
            'agent_name' => fake()->name(),
            'agent_contact' => fake()->e164PhoneNumber(),
        ]);
    }
}
