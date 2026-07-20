<?php

namespace App\Events\Returns;

use App\Models\Admin;
use App\Models\ReturnRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ReturnRequest $returnRequest,
        public readonly ?Admin $admin = null,
    ) {}
}
