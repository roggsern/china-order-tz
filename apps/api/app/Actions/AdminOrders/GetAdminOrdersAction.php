<?php

namespace App\Actions\AdminOrders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAdminOrdersAction
{
    public function handle(?string $status = null): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['user', 'payments', 'refundTransactions', 'statusHistory'])
            ->latest();

        if ($status !== null && $status !== '') {
            $normalized = strtolower(trim($status));
            if (OrderStatus::tryFrom($normalized) === null && $normalized !== 'all') {
                // Unknown filter — return empty rather than inventing status.
                $query->whereRaw('1 = 0');
            } elseif ($normalized !== 'all') {
                $query->where('status', $normalized);
            }
        }

        return $query->paginate(15);
    }
}
