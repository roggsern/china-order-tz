<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnItem>
 */
class ReturnItemFactory extends Factory
{
    protected $model = ReturnItem::class;

    public function definition(): array
    {
        return [
            'return_request_id' => ReturnRequest::factory(),
            'order_item_id' => OrderItem::factory(),
            'quantity' => 1,
            'reason' => 'Damaged',
            'condition' => null,
            'resolution' => null,
            'refund_amount' => fake()->randomFloat(2, 10000, 100000),
            'replacement_requested' => false,
        ];
    }
}
