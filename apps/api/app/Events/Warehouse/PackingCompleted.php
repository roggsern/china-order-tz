<?php

namespace App\Events\Warehouse;

use App\Models\Admin;
use App\Models\WarehousePackingRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PackingCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WarehousePackingRecord $packingRecord,
        public Admin $admin,
    ) {}
}
