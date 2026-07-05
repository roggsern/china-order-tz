<?php

namespace App\Actions\AdminOrders;

use App\Models\Order;

class ShowOrderAction
{
    public function handle(Order $order): Order
    {
        return $order->load([
            'user',
            'coupon',
            'items.product',
            'items.variant',
            'payments',
            'shippingAddress',
        ]);
    }
}
