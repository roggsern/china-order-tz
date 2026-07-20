<?php

namespace App\Events\Crm;

use App\Models\CustomerMetric;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerMetricsUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CustomerMetric $metrics,
    ) {}
}
