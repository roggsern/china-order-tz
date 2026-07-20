<?php

namespace Database\Seeders;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\DeliveryOption;
use App\Models\Order;
use App\Services\Fulfillment\FulfillmentStrategyResolver;
use Illuminate\Database\Seeder;

/**
 * Demo delivery options for paid orders.
 * Does not create shipments or calculate shipping prices.
 */
class DeliveryOptionSeeder extends Seeder
{
    public function run(): void
    {
        $orders = Order::query()
            ->where('status', OrderStatus::Paid->value)
            ->whereDoesntHave('deliveryOption')
            ->with(['items.product.supplier', 'user'])
            ->limit(10)
            ->get();

        if ($orders->isEmpty()) {
            $this->command?->warn('DeliveryOptionSeeder skipped: no paid orders without delivery options.');

            return;
        }

        $chinaResolver = app(FulfillmentStrategyResolver::class);

        foreach ($orders as $index => $order) {
            if ($order->user === null) {
                continue;
            }

            try {
                $requiresChina = $chinaResolver->orderRequiresChina($order);

                if ($requiresChina) {
                    if ($index % 2 === 0) {
                        DeliveryOption::query()->create([
                            'order_id' => $order->id,
                            'delivery_type' => DeliveryType::CompanyShipping,
                            'shipping_method' => DeliveryShippingMethod::Air,
                            'delivery_status' => DeliveryOptionStatus::Pending,
                            'notes' => 'Demo: company air freight (pricing later).',
                        ]);
                    } else {
                        DeliveryOption::query()->create([
                            'order_id' => $order->id,
                            'delivery_type' => DeliveryType::CustomerAgent,
                            'shipping_method' => null,
                            'delivery_status' => DeliveryOptionStatus::Confirmed,
                            'agent_name' => 'Demo Clearing Agent',
                            'agent_contact' => '+255700000001',
                            'notes' => 'Demo: handover to customer agent.',
                            'confirmed_at' => now(),
                        ]);
                    }
                } else {
                    if ($index % 2 === 0) {
                        DeliveryOption::query()->create([
                            'order_id' => $order->id,
                            'delivery_type' => DeliveryType::SelfPickup,
                            'delivery_status' => DeliveryOptionStatus::Pending,
                            'notes' => 'Demo: self pickup request recorded.',
                        ]);
                    } else {
                        DeliveryOption::query()->create([
                            'order_id' => $order->id,
                            'delivery_type' => DeliveryType::NegotiatedDelivery,
                            'delivery_status' => DeliveryOptionStatus::Pending,
                            'notes' => 'Demo: delivery price negotiated offline.',
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $this->command?->warn("DeliveryOptionSeeder skipped order {$order->id}: {$e->getMessage()}");
            }
        }
    }
}
