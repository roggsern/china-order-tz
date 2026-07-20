<?php

namespace App\Events\Crm;

use App\Enums\CustomerLifecycleStatus;
use App\Models\Admin;
use App\Models\CustomerProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CustomerProfile $profile,
        public readonly ?CustomerLifecycleStatus $from,
        public readonly CustomerLifecycleStatus $to,
        public readonly ?Admin $admin = null,
        public readonly ?string $reason = null,
    ) {}
}
