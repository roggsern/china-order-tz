<?php

namespace Database\Factories;

use App\Enums\ReceivingStatus;
use App\Models\PurchaseOrder;
use App\Models\ReceivingRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivingRecord>
 */
class ReceivingRecordFactory extends Factory
{
    protected $model = ReceivingRecord::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'received_by' => null,
            'status' => ReceivingStatus::Pending,
            'received_at' => null,
            'notes' => null,
        ];
    }
}
