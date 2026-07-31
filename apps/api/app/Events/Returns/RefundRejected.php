<?php

namespace App\Events\Returns;

use App\Models\Admin;
use App\Models\RefundTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly RefundTransaction $refund,
        public readonly Admin $admin,
        public readonly ?string $reason = null,
    ) {}
}
