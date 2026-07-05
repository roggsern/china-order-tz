<?php

namespace App\Actions\AdminOrders;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAdminOrdersAction
{
    public function handle(): LengthAwarePaginator
    {
        return Order::query()
            ->with(['user'])
            ->latest()
            ->paginate(15);
    }
}
