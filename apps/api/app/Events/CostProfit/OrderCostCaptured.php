<?php

namespace App\Events\CostProfit;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderCostSnapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCostCaptured
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<OrderCostSnapshot>  $snapshots
     */
    public function __construct(
        public readonly Order $order,
        public readonly array $snapshots,
        public readonly ?Admin $admin = null,
    ) {}
}
