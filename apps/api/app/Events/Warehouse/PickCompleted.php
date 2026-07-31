<?php

namespace App\Events\Warehouse;

use App\Models\Admin;
use App\Models\WarehousePickList;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PickCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WarehousePickList $pickList,
        public Admin $admin,
    ) {}
}
