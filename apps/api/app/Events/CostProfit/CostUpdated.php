<?php

namespace App\Events\CostProfit;

use App\Models\Admin;
use App\Models\OrderCostSnapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CostUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $before
     */
    public function __construct(
        public readonly OrderCostSnapshot $snapshot,
        public readonly array $before,
        public readonly ?Admin $admin = null,
    ) {}
}
