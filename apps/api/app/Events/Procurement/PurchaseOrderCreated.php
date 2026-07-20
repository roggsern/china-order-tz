<?php

namespace App\Events\Procurement;

use App\Models\Admin;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PurchaseOrder $purchaseOrder,
        public readonly ?Admin $admin = null,
    ) {}
}
