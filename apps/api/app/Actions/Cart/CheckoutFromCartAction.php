<?php

namespace App\Actions\Cart;

use App\Enums\CartStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutFromCartAction
{
    /**
     * @return array{order: Order, payment: Payment}
     */
    public function handle(User $user): array
    {
        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::Active)
            ->first();

        if ($cart === null) {
            $this->throwValidationError('Cart not found.');
        }

        $cart->load(['items.product', 'items.variant']);

        if ($cart->items->isEmpty()) {
            $this->throwValidationError('Cart is empty.');
        }

        foreach ($cart->items as $item) {
            $this->validateInventory($item);
        }

        return DB::transaction(function () use ($user, $cart) {
            $subtotal = '0.00';
            $orderItemsData = [];

            foreach ($cart->items as $item) {
                $lineTotal = bcmul((string) $item->unit_price, (string) $item->quantity, 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);

                $orderItemsData[] = [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product->name,
                    'variant_name' => $item->variant?->name,
                    'sku' => $item->variant?->sku ?? $item->product->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $lineTotal,
                ];
            }

            $order = Order::query()->create([
                'user_id' => $user->id,
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

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'method' => PaymentMethod::Cash,
                'status' => PaymentStatus::Pending,
                'amount' => $subtotal,
                'currency' => $order->currency,
            ]);

            return [
                'order' => $order->load(['items.product', 'items.variant']),
                'payment' => $payment->load(['order']),
            ];
        });
    }

    private function validateInventory(CartItem $item): void
    {
        $inventory = Inventory::query()
            ->where('product_id', $item->product_id)
            ->when(
                $item->product_variant_id,
                fn ($query) => $query->where('product_variant_id', $item->product_variant_id),
                fn ($query) => $query->whereNull('product_variant_id'),
            )
            ->first();

        if ($inventory === null || $inventory->availableQuantity() < $item->quantity) {
            $this->throwValidationError(
                "Insufficient stock for {$item->product->name}.",
            );
        }
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'COT-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    private function throwValidationError(string $message): never
    {
        $exception = ValidationException::withMessages([
            'cart' => [$message],
        ]);

        $exception->response = response()->json([
            'success' => false,
            'message' => $message,
        ], 422);

        throw $exception;
    }
}
