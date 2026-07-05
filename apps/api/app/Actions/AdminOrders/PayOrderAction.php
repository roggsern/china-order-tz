<?php

namespace App\Actions\AdminOrders;

use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayOrderAction
{
    public function handle(Order $order): Order
    {
        if ($order->status === OrderStatus::Paid) {
            $this->throwValidationError('Order is already paid.');
        }

        if ($order->status !== OrderStatus::Pending) {
            $this->throwValidationError('Only pending orders can be paid.');
        }

        return DB::transaction(function () use ($order) {
            $order->load('items');

            foreach ($order->items as $item) {
                $inventory = $this->findInventoryForItem($item);

                if ($inventory === null || $inventory->availableQuantity() < $item->quantity) {
                    $this->throwValidationError(
                        "Insufficient stock for {$item->product_name}.",
                    );
                }
            }

            foreach ($order->items as $item) {
                $inventory = $this->findInventoryForItem($item);

                $inventory->decrement('quantity', $item->quantity);

                $inventory->movements()->create([
                    'quantity' => -$item->quantity,
                    'type' => 'sale',
                    'reason' => "Order {$order->order_number}",
                ]);
            }

            $order->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
            ]);

            return $order->fresh()->load([
                'user',
                'coupon',
                'items.product',
                'items.variant',
                'payments',
                'shippingAddress',
            ]);
        });
    }

    private function findInventoryForItem(OrderItem $item): ?Inventory
    {
        return Inventory::query()
            ->where('product_id', $item->product_id)
            ->when(
                $item->product_variant_id,
                fn ($query) => $query->where('product_variant_id', $item->product_variant_id),
                fn ($query) => $query->whereNull('product_variant_id'),
            )
            ->lockForUpdate()
            ->first();
    }

    private function throwValidationError(string $message): never
    {
        $exception = ValidationException::withMessages([
            'order' => [$message],
        ]);

        $exception->response = response()->json([
            'success' => false,
            'message' => $message,
        ], 422);

        throw $exception;
    }
}
