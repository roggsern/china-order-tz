<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'purchase_number' => 'PO-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'status' => PurchaseOrderStatus::Draft,
            'currency' => 'TZS',
            'notes' => null,
            'ordered_at' => null,
            'confirmed_at' => null,
            'completed_at' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Confirmed,
            'ordered_at' => now()->subDay(),
            'confirmed_at' => now(),
        ]);
    }
}
