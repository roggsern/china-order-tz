<?php

namespace App\Events\Crm;

use App\Models\Admin;
use App\Models\CustomerProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerProfileCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CustomerProfile $profile,
        public readonly ?Admin $admin = null,
    ) {}
}
