<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStatusHistorySource;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\FulfillmentStatusHistory;

class FulfillmentStatusHistoryRecorder
{
    public function record(
        Fulfillment $fulfillment,
        ?FulfillmentStatus $fromStatus,
        FulfillmentStatus $toStatus,
        FulfillmentStatusUpdateContext $context,
    ): FulfillmentStatusHistory {
        return FulfillmentStatusHistory::query()->create([
            'fulfillment_id' => $fulfillment->id,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus->value,
            'changed_by' => $context->admin?->id,
            'source' => $context->source,
            'notes' => $context->notes,
        ]);
    }
}
