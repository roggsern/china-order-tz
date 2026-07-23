<?php

namespace App\Actions\AdminOrders;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Services\CostProfit\ProfitEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Inventory\DTOs\InventoryCommitmentContext;
use App\Services\Inventory\InventoryCommitmentService;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Admin mark-paid — inventory commitment + OrderLifecycleEngine paid transition + fulfillment.
 *
 * LOCKED MODULE NOTE (Lifecycle Closure #2): status write uses OrderLifecycleEngine.
 * Inventory writes go through InventoryCommitmentService → MutationGate (ADR 055).
 */
class PayOrderAction
{
    public function __construct(
        private readonly FulfillmentEngine $fulfillmentEngine,
        private readonly ProfitEngine $profitEngine,
        private readonly OrderLifecycleEngine $lifecycle,
        private readonly InventoryCommitmentService $inventoryCommitment,
    ) {}

    public function handle(Order $order): Order
    {
        if ($order->status === OrderStatus::Paid) {
            $this->throwValidationError('Order is already paid.');
        }

        if (! in_array($order->status, [OrderStatus::Pending, OrderStatus::PendingPayment], true)) {
            $this->throwValidationError('Only pending orders can be paid.');
        }

        /** @var Admin|null $admin */
        $admin = Auth::user() instanceof Admin ? Auth::user() : null;

        return DB::transaction(function () use ($order, $admin): Order {
            $order->load('items.product', 'items.variant');

            try {
                $this->inventoryCommitment->commitForOrder(new InventoryCommitmentContext(
                    order: $order,
                    actor: $admin,
                    source: 'admin_pay',
                    metadata: ['path' => 'PayOrderAction'],
                    strict: true,
                ));
            } catch (ValidationException $e) {
                $message = collect($e->errors())->flatten()->first()
                    ?? 'Insufficient stock for one or more order items.';
                $this->throwValidationError((string) $message);
            }

            $context = new OrderLifecycleContext(
                source: 'admin_pay',
                reason: 'Admin marked order paid',
                admin: $admin,
                metadata: ['path' => 'PayOrderAction'],
            );

            $paid = $this->lifecycle->markPaid($order, $context)->load([
                'user',
                'coupon',
                'items.product',
                'items.variant',
                'payments',
                'shippingAddress',
                'fulfillment',
                'statusHistory',
            ]);

            try {
                $this->profitEngine->calculateForOrder($paid);
            } catch (\Throwable $e) {
                Log::warning('profit.calculate_after_admin_pay_failed', [
                    'order_id' => $paid->id,
                    'message' => $e->getMessage(),
                ]);
            }

            try {
                $this->fulfillmentEngine->createForOrder($paid);
            } catch (\Throwable $e) {
                Log::warning('fulfillment.create_after_admin_pay_failed', [
                    'order_id' => $paid->id,
                    'message' => $e->getMessage(),
                ]);
            }

            return $paid->fresh([
                'user',
                'coupon',
                'items.product',
                'items.variant',
                'payments',
                'shippingAddress',
                'fulfillment',
                'statusHistory',
            ]);
        });
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
