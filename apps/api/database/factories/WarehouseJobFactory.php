<?php

namespace Database\Factories;

use App\Enums\WarehouseJobStatus;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\WarehouseJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseJob>
 */
class WarehouseJobFactory extends Factory
{
    protected $model = WarehouseJob::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'fulfillment_id' => Fulfillment::factory(),
            'job_number' => 'COTZ-WH-'.now()->format('Ymd').'-'.substr(str_replace('-', '', (string) fake()->uuid()), 0, 6),
            'status' => WarehouseJobStatus::Pending,
            'picker_id' => null,
            'packer_id' => null,
            'picked_at' => null,
            'packed_at' => null,
            'ready_at' => null,
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (WarehouseJob $job): void {
            if ($job->fulfillment_id && ! $job->order_id) {
                $job->order_id = Fulfillment::query()->whereKey($job->fulfillment_id)->value('order_id');
            }
        });
    }

    public function picking(): static
    {
        return $this->state(fn () => [
            'status' => WarehouseJobStatus::Picking,
        ]);
    }

    public function readyToShip(): static
    {
        return $this->state(fn () => [
            'status' => WarehouseJobStatus::ReadyToShip,
            'picked_at' => now()->subHours(3),
            'packed_at' => now()->subHour(),
            'ready_at' => now(),
        ]);
    }
}
