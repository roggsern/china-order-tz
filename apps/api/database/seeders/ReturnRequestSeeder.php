<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\RefundTransactionStatus;
use App\Enums\ReturnItemResolution;
use App\Enums\ReturnRequestStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundTransaction;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReturnRequestSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::query()->first() ?? Admin::factory()->superAdmin()->create([
            'email' => 'returns-seed-admin@example.com',
        ]);

        $customer = User::query()->first() ?? User::factory()->create([
            'email' => 'returns-seed-customer@example.com',
            'name' => 'Returns Seed Customer',
        ]);

        // Idempotent: skip when seed markers already exist
        if (ReturnRequest::query()->where('reason', 'Item arrived damaged')->exists()
            && ReturnRequest::query()->where('reason', 'Wrong item sent')->exists()
            && RefundTransaction::query()->where('reference', 'REF-SEED-001')->exists()) {
            return;
        }

        $this->seedRequestedReturn($customer);
        $this->seedCompletedReturnWithRefund($customer, $admin);
    }

    private function seedRequestedReturn(User $customer): void
    {
        if (ReturnRequest::query()->where('reason', 'Item arrived damaged')->exists()) {
            return;
        }

        $order = $this->freshDeliveredOrder($customer, quantity: 2, unitPrice: 50000);
        $item = $order->items->first();
        if ($item === null) {
            return;
        }

        // Avoid open-return conflicts: only attach to orders with no active returns
        if ($this->orderHasOpenReturn($order->id)) {
            $order = $this->freshDeliveredOrder($customer, quantity: 2, unitPrice: 50000);
            $item = $order->items->first();
            if ($item === null) {
                return;
            }
        }

        $requested = ReturnRequest::query()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'status' => ReturnRequestStatus::Requested,
            'reason' => 'Item arrived damaged',
            'description' => 'Screen cracked on arrival.',
            'customer_notes' => 'Please advise next steps.',
        ]);

        ReturnItem::query()->create([
            'return_request_id' => $requested->id,
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason' => 'Damaged',
            'refund_amount' => 50000,
            'replacement_requested' => false,
        ]);
    }

    private function seedCompletedReturnWithRefund(User $customer, Admin $admin): void
    {
        if (ReturnRequest::query()->where('reason', 'Wrong item sent')->exists()
            && RefundTransaction::query()->where('reference', 'REF-SEED-001')->exists()) {
            return;
        }

        $order = $this->freshDeliveredOrder($customer, quantity: 1, unitPrice: 75000);
        $item = $order->items->first();
        if ($item === null) {
            return;
        }

        if ($this->orderHasOpenReturn($order->id)) {
            $order = $this->freshDeliveredOrder($customer, quantity: 1, unitPrice: 75000);
            $item = $order->items->first();
            if ($item === null) {
                return;
            }
        }

        $completed = ReturnRequest::query()->firstOrCreate(
            [
                'order_id' => $order->id,
                'reason' => 'Wrong item sent',
            ],
            [
                'customer_id' => $customer->id,
                'status' => ReturnRequestStatus::Completed,
                'description' => 'Received different colour.',
                'admin_notes' => 'Inspected and approved refund.',
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(3),
                'completed_at' => now()->subDay(),
            ]
        );

        if (! ReturnItem::query()->where('return_request_id', $completed->id)->exists()) {
            ReturnItem::query()->create([
                'return_request_id' => $completed->id,
                'order_item_id' => $item->id,
                'quantity' => 1,
                'reason' => 'Wrong item',
                'condition' => 'unopened',
                'resolution' => ReturnItemResolution::Refund,
                'refund_amount' => 75000,
                'replacement_requested' => false,
            ]);
        }

        RefundTransaction::query()->firstOrCreate(
            ['reference' => 'REF-SEED-001'],
            [
                'return_request_id' => $completed->id,
                'order_id' => $order->id,
                'amount' => 75000,
                'currency' => 'TZS',
                'status' => RefundTransactionStatus::Completed,
                'method' => 'manual',
                'notes' => 'Seeded completed refund.',
            ]
        );
    }

    private function freshDeliveredOrder(User $customer, int $quantity, int $unitPrice): Order
    {
        $lineTotal = $quantity * $unitPrice;

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Delivered,
            'paid_at' => now()->subDays(10),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_price_snapshot' => $unitPrice,
            'line_total' => $lineTotal,
            'total_price' => $lineTotal,
        ]);

        return $order->load('items');
    }

    private function orderHasOpenReturn(string $orderId): bool
    {
        return ReturnRequest::query()
            ->where('order_id', $orderId)
            ->whereNotIn('status', [
                ReturnRequestStatus::Completed->value,
                ReturnRequestStatus::Rejected->value,
                ReturnRequestStatus::Cancelled->value,
            ])
            ->exists();
    }
}
