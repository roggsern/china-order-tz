<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentStatusHistorySource;
use App\Models\Admin;

final class FulfillmentStatusUpdateContext
{
    public function __construct(
        public readonly FulfillmentStatusHistorySource $source = FulfillmentStatusHistorySource::System,
        public readonly ?Admin $admin = null,
        public readonly ?string $notes = null,
    ) {}
}
