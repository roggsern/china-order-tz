<?php

namespace App\Events\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Models\Admin;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PurchaseOrder $purchaseOrder,
        public readonly PurchaseOrderStatus $from,
        public readonly PurchaseOrderStatus $to,
        public readonly ?Admin $admin = null,
    ) {}
}
