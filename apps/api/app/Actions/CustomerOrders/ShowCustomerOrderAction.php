<?php

namespace App\Actions\CustomerOrders;

use App\Models\Order;
use App\Models\User;

class ShowCustomerOrderAction
{
    public function handle(Order $order, User $user): Order
    {
        if ($order->user_id !== $user->id) {
            abort(404);
        }

        return $order->load([
            'items',
            'items.product.supplier',
            'payments',
        ]);
    }
}
