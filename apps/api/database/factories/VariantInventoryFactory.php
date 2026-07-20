<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\VariantInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VariantInventory>
 */
class VariantInventoryFactory extends Factory
{
    protected $model = VariantInventory::class;

    public function definition(): array
    {
        $onHand = fake()->numberBetween(0, 200);
        $reserved = fake()->numberBetween(0, (int) floor($onHand * 0.3));

        return [
            'product_variant_id' => ProductVariant::factory(),
            'warehouse_code' => 'MAIN',
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'reorder_level' => 5,
            'safety_stock' => 0,
            'is_active' => true,
        ];
    }

    public function warehouse(string $code): static
    {
        return $this->state(fn () => ['warehouse_code' => strtoupper($code)]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => [
            'on_hand' => 3,
            'reserved' => 0,
            'reorder_level' => 5,
        ]);
    }
}
