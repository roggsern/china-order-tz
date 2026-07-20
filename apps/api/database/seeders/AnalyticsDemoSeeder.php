<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\WarehouseJobStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WarehouseJob;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds a spread of dated orders/payments/warehouse/shipments for analytics charts.
 * Does not invent fake ledger mutations — creates realistic historical rows only.
 */
class AnalyticsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->first() ?? User::factory()->create([
            'email' => 'analytics-customer@example.com',
            'name' => 'Analytics Customer',
        ]);

        for ($day = 29; $day >= 0; $day--) {
            $at = Carbon::now()->subDays($day)->setTime(10 + ($day % 5), 15);

            $order = Order::factory()->create([
                'user_id' => $customer->id,
                'status' => $day % 7 === 0
                    ? OrderStatus::Cancelled
                    : ($day % 5 === 0 ? OrderStatus::PendingPayment : OrderStatus::Paid),
                'total' => 25000 + ($day * 1500),
                'subtotal' => 25000 + ($day * 1500),
                'is_demo' => false,
                'placed_at' => $at,
                'paid_at' => $day % 5 === 0 ? null : $at->copy()->addHour(),
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            OrderItem::factory()->create([
                'order_id' => $order->id,
                'quantity' => 1 + ($day % 3),
                'unit_price' => 20000,
                'unit_price_snapshot' => 20000,
                'line_total' => 20000 * (1 + ($day % 3)),
                'total_price' => 20000 * (1 + ($day % 3)),
                'product_name_snapshot' => 'Analytics Demo Product '.(($day % 4) + 1),
                'created_at' => $at,
            ]);

            if ($order->status === OrderStatus::Paid) {
                PaymentTransaction::factory()->create([
                    'order_id' => $order->id,
                    'amount' => $order->total,
                    'status' => PaymentTransactionStatus::Successful,
                    'created_at' => $at->copy()->addMinutes(30),
                ]);
            }
        }

        // Active warehouse / shipment snapshots for dashboard cards
        foreach ([
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::ReadyToShip,
        ] as $status) {
            if (! WarehouseJob::query()->where('status', $status->value)->exists()) {
                WarehouseJob::factory()->create(['status' => $status]);
            }
        }

        foreach ([
            ShipmentLifecycleStatus::Booked,
            ShipmentLifecycleStatus::InTransit,
            ShipmentLifecycleStatus::Delivered,
        ] as $status) {
            if (! Shipment::query()->where('status', $status->value)->exists()) {
                Shipment::factory()->create([
                    'status' => $status,
                    'delivered_at' => $status === ShipmentLifecycleStatus::Delivered ? now() : null,
                ]);
            }
        }
    }
}
