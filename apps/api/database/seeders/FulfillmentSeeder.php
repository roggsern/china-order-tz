<?php

namespace Database\Seeders;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentEngine;
use Illuminate\Database\Seeder;

/**
 * Paid demo orders with fulfillment records.
 * Does not call payment gateways or create shipments.
 */
class FulfillmentSeeder extends Seeder
{
    public function run(): void
    {
        /** @var FulfillmentEngine $engine */
        $engine = app(FulfillmentEngine::class);

        $this->ensurePaidDemoOrders();

        $paidOrders = Order::query()
            ->where('status', OrderStatus::Paid->value)
            ->whereDoesntHave('fulfillment')
            ->with(['items.product.supplier', 'user'])
            ->limit(10)
            ->get();

        if ($paidOrders->isEmpty()) {
            $this->command?->warn('FulfillmentSeeder skipped: no paid orders without fulfillment.');

            return;
        }

        $admin = Admin::query()->first();

        foreach ($paidOrders as $index => $order) {
            try {
                $fulfillment = $engine->createForOrder($order);

                if ($index === 0 && $admin !== null) {
                    $engine->updateStatus($fulfillment, [
                        'status' => FulfillmentStatus::Processing->value,
                        'assigned_to' => $admin->id,
                        'notes' => 'Demo fulfillment assigned for admin queue.',
                    ]);
                }
            } catch (\Throwable $e) {
                $this->command?->warn("FulfillmentSeeder skipped order {$order->id}: {$e->getMessage()}");
            }
        }
    }

    private function ensurePaidDemoOrders(): void
    {
        $existingPaid = Order::query()->where('status', OrderStatus::Paid->value)->count();
        if ($existingPaid >= 2) {
            return;
        }

        $user = User::query()->first() ?? User::factory()->create([
            'email' => 'fulfillment-demo@chinaorder.test',
        ]);

        $localProduct = Product::query()
            ->where('fulfillment_source', 'buy_from_tz')
            ->first();

        $chinaProduct = Product::query()
            ->where('fulfillment_source', 'imported_from_china')
            ->first()
            ?? Product::query()->first();

        if ($localProduct === null && $chinaProduct === null) {
            $this->command?->warn('FulfillmentSeeder: no products available to seed paid orders.');

            return;
        }

        if ($existingPaid < 1 && $localProduct !== null) {
            $this->createPaidOrderWithItem($user, $localProduct, 'COT-FUL-LOCAL');
        }

        if (Order::query()->where('status', OrderStatus::Paid->value)->count() < 2 && $chinaProduct !== null) {
            $this->createPaidOrderWithItem($user, $chinaProduct, 'COT-FUL-CHINA');
        }
    }

    private function createPaidOrderWithItem(User $user, Product $product, string $orderNumber): void
    {
        if (Order::query()->where('order_number', $orderNumber)->exists()) {
            Order::query()
                ->where('order_number', $orderNumber)
                ->update([
                    'status' => OrderStatus::Paid->value,
                    'paid_at' => now(),
                ]);

            return;
        }

        $unitPrice = (float) ($product->price ?? 50000);
        $qty = 1;
        $total = $unitPrice * $qty;

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => $orderNumber,
            'status' => OrderStatus::Paid,
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total' => $total,
            'currency' => 'TZS',
            'is_demo' => true,
            'placed_at' => now()->subDay(),
            'paid_at' => now()->subHours(2),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_name_snapshot' => $product->name,
            'sku' => $product->sku,
            'sku_snapshot' => $product->sku,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $total,
            'total_price' => $total,
            'currency' => 'TZS',
        ]);
    }
}
