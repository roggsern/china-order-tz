<?php

namespace App\Actions\CustomerDashboard;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

class ShowCustomerDashboardAction
{
    /**
     * @return array{
     *     customer: array{id: string, name: string},
     *     summary: array{
     *         active_orders: int,
     *         in_transit_orders: int,
     *         pending_payments: int,
     *         completed_orders: int
     *     },
     *     recent_orders: Collection<int, array{
     *         id: string,
     *         order_number: string,
     *         source: string,
     *         status: string,
     *         created_at: \Illuminate\Support\Carbon|null
     *     }>,
     *     quick_actions: list<array{label: string}>
     * }
     */
    public function handle(User $user): array
    {
        $ordersQuery = Order::query()->where('user_id', $user->id);

        return [
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'summary' => [
                'active_orders' => (clone $ordersQuery)->whereIn('status', [
                    OrderStatus::Paid,
                    OrderStatus::Confirmed,
                    OrderStatus::Processing,
                ])->count(),
                'in_transit_orders' => (clone $ordersQuery)->where('status', OrderStatus::Shipped)->count(),
                'pending_payments' => Payment::query()
                    ->where('user_id', $user->id)
                    ->where('status', PaymentStatus::Pending)
                    ->count(),
                'completed_orders' => (clone $ordersQuery)->where('status', OrderStatus::Delivered)->count(),
            ],
            'recent_orders' => Order::query()
                ->where('user_id', $user->id)
                ->with(['items.product.commerceChannel'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'source' => $order->resolveSource(),
                    'status' => $order->status->value,
                    'created_at' => $order->created_at,
                ]),
            'quick_actions' => [
                ['label' => 'Order From China'],
                ['label' => 'Buy From Dar'],
                ['label' => 'My Orders'],
                ['label' => 'My Payments'],
            ],
        ];
    }
}
