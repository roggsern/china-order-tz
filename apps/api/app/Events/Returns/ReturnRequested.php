<?php

namespace App\Events\Returns;

use App\Models\ReturnRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ReturnRequest $returnRequest) {}
}
