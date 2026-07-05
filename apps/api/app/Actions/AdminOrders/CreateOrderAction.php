<?php

namespace App\Actions\AdminOrders;

use App\Enums\OrderStatus;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function handle(StoreOrderRequest $request): Order
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $subtotal = '0.00';
            $orderItemsData = [];

            foreach ($validated['items'] as $item) {
                $product = Product::query()->findOrFail($item['product_id']);

                $variant = filled($item['variant_id'] ?? null)
                    ? ProductVariant::query()->with('product')->findOrFail($item['variant_id'])
                    : null;

                $unitPrice = $variant
                    ? $variant->effectivePrice()
                    : (string) $product->price;

                $quantity = $item['quantity'];
                $lineTotal = bcmul($unitPrice, (string) $quantity, 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                ];
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

            return $order->load([
                'user',
                'coupon',
                'items.product',
                'items.variant',
                'payments',
                'shippingAddress',
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
