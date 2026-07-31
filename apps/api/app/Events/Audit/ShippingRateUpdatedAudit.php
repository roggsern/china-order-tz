<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;

class ShippingRateUpdatedAudit extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function fromRate(
        ShippingMethod $method,
        ShippingRate $rate,
        ?array $oldValues,
        ?array $newValues,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::ShippingRateUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: ShippingRate::class,
            subjectId: $rate->id,
            description: sprintf('Shipping rate for "%s" was updated.', $method->code),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: [
                'method' => $method->code,
                'method_name' => $method->name,
                'shipping_method_id' => $method->id,
                'shipping_rate_id' => $rate->id,
            ],
        );
    }
}
