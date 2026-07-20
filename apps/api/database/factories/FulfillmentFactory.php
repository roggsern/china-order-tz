<?php

namespace Database\Factories;

use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Models\Fulfillment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fulfillment>
 */
class FulfillmentFactory extends Factory
{
    protected $model = Fulfillment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'strategy' => FulfillmentStrategy::Local,
            'status' => FulfillmentStatus::Pending,
            'assigned_to' => null,
            'started_at' => null,
            'completed_at' => null,
            'notes' => null,
        ];
    }

    public function china(): static
    {
        return $this->state(fn () => [
            'strategy' => FulfillmentStrategy::China,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => FulfillmentStatus::Processing,
            'started_at' => now(),
        ]);
    }
}
