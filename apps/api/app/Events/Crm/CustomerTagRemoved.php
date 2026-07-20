<?php

namespace App\Events\Crm;

use App\Models\Admin;
use App\Models\CustomerProfile;
use App\Models\CustomerTag;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerTagRemoved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CustomerProfile $profile,
        public readonly CustomerTag $tag,
        public readonly ?Admin $admin = null,
    ) {}
}
