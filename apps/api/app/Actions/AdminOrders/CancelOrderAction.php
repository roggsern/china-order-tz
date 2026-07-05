<?php

namespace App\Actions\AdminOrders;

use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrderAction
{
    public function handle(Order $order): Order
    {
        if ($order->status !== OrderStatus::Paid) {
            $this->throwValidationError('Only paid orders can be cancelled.');
        }

        return DB::transaction(function () use ($order) {
            $order->load('items');

            foreach ($order->items as $item) {
                $inventory = $this->findInventoryForItem($item);

                if ($inventory === null) {
                    $this->throwValidationError(
                        "Inventory record not found for {$item->product_name}.",
                    );
                }

                $inventory->increment('quantity', $item->quantity);

                $inventory->movements()->create([
                    'quantity' => $item->quantity,
                    'type' => 'restock',
                    'reason' => "Order {$order->order_number} cancelled",
                ]);
            }

            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $order->fresh()->load([
                'user',
                'items.product.inventory',
                'items.variant.inventory',
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
