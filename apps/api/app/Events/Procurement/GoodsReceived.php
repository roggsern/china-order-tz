<?php

namespace App\Events\Procurement;

use App\Models\Admin;
use App\Models\ReceivingRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GoodsReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ReceivingRecord $receivingRecord,
        public readonly ?Admin $admin = null,
    ) {}
}
