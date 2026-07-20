<?php

namespace App\Actions\CustomerOrders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCustomerOrdersAction
{
    public function handle(User $user, int $perPage, string $filter): LengthAwarePaginator
    {
        $query = Order::query()
            ->where('user_id', $user->id)
            ->with(['items.product.supplier']);

        if ($filter === 'active') {
            $query->whereIn('status', [
                OrderStatus::Pending,
                OrderStatus::PendingPayment,
                OrderStatus::Paid,
                OrderStatus::Confirmed,
                OrderStatus::Processing,
                OrderStatus::Shipped,
            ]);
        } elseif ($filter === 'completed') {
            $query->whereIn('status', [
                OrderStatus::Delivered,
                OrderStatus::Completed,
            ]);
        }

        return $query->latest()->paginate($perPage);
    }
}
