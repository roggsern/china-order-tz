<?php

namespace App\Events\Procurement;

use App\Models\Admin;
use App\Models\Supplier;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly Supplier $supplier,
        public readonly array $before,
        public readonly array $after,
        public readonly ?Admin $admin = null,
    ) {}
}
