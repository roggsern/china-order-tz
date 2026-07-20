<?php

namespace App\Events\CostProfit;

use App\Models\ProfitRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowMarginDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ProfitRecord $profitRecord,
        public readonly float $threshold,
    ) {}
}
