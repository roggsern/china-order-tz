<?php

namespace App\Actions\AdminOrders;

use App\Enums\OrderStatus;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use App\Services\Orders\OrderSnapshotEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function __construct(
        private readonly OrderSnapshotEngine $snapshotEngine,
        private readonly OrderLifecycleEngine $lifecycle,
    ) {}

    public function handle(StoreOrderRequest $request): Order
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $subtotal = '0.00';
            $orderItemsData = [];

            foreach ($validated['items'] as $item) {
                $product = Product::query()->findOrFail($item['product_id']);

                $variant = filled($item['variant_id'] ?? null)
                    ? ProductVariant::query()->with(['product', 'attributeValues.attribute'])->findOrFail($item['variant_id'])
                    : null;

                $unitPrice = $variant
                    ? $variant->effectivePrice()
                    : (string) $product->price;

                $quantity = (int) $item['quantity'];
                $payload = $this->snapshotEngine->snapshotFromCatalog(
                    $product,
                    $variant,
                    $quantity,
                    $unitPrice,
                    'TZS',
                );

                $subtotal = bcadd($subtotal, (string) $payload['line_total'], 2);
                $orderItemsData[] = $payload;
            }

            $order = Order::query()->create([
                'user_id' => $validated['user_id'],
                'coupon_id' => $validated['coupon_id'] ?? null,
                'order_number' => $this->generateOrderNumber(),
                'status' => OrderStatus::Pending,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'shipping_amount' => 0,
                'total' => $subtotal,
                'placed_at' => now(),
            ]);

            $order->items()->createMany($orderItemsData);

            ShippingAddress::query()
                ->whereKey($validated['shipping_address_id'])
                ->update(['order_id' => $order->id]);

            /** @var Admin|null $admin */
            $admin = Auth::user() instanceof Admin ? Auth::user() : null;
            $this->lifecycle->recordCreated(
                $order,
                $admin !== null
                    ? OrderLifecycleContext::admin($admin, 'admin_create', 'Admin created order')
                    : OrderLifecycleContext::system('admin_create', 'Admin created order'),
            );

            return $order->load([
                'user',
                'coupon',
                'items.product',
                'items.variant',
                'payments',
                'shippingAddress',
                'statusHistory',
            ]);
        });
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'COT-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
