<?php

namespace App\Events\Crm;

use App\Models\Admin;
use App\Models\CustomerProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerProfileUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $before
     */
    public function __construct(
        public readonly CustomerProfile $profile,
        public readonly array $before,
        public readonly ?Admin $admin = null,
    ) {}
}
