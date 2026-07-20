<?php

namespace Database\Factories;

use App\Enums\ReturnRequestStatus;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnRequest>
 */
class ReturnRequestFactory extends Factory
{
    protected $model = ReturnRequest::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->state(['status' => 'delivered']),
            'customer_id' => User::factory(),
            'status' => ReturnRequestStatus::Requested,
            'reason' => 'Damaged item',
            'description' => fake()->sentence(),
            'customer_notes' => fake()->optional()->sentence(),
            'admin_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
            'completed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ReturnRequest $return): void {
            if ($return->customer_id && $return->order_id) {
                // Keep customer aligned with order when both set via states.
            }
        })->afterCreating(function (ReturnRequest $return): void {
            $order = $return->order;
            if ($order && $return->customer_id !== $order->user_id) {
                $return->forceFill(['customer_id' => $order->user_id])->save();
            }
        });
    }
}
