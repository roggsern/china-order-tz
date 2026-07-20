<?php

namespace App\Events\Procurement;

use App\Models\Admin;
use App\Models\Supplier;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Supplier $supplier,
        public readonly ?Admin $admin = null,
    ) {}
}
