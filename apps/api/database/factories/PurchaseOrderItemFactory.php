<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'quantity_ordered' => fake()->numberBetween(1, 50),
            'quantity_received' => 0,
            'unit_cost' => fake()->randomFloat(2, 1000, 100000),
            'currency' => 'TZS',
        ];
    }
}
