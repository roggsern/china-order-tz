<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\ProductShippingOption;

class ShippingOptionUpdated extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function fromOption(
        ProductShippingOption $option,
        ?array $oldValues,
        ?array $newValues,
        ?Admin $admin = null,
        string $action = 'updated',
    ): self {
        return self::make(
            type: ActivityEventType::ShippingOptionUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: ProductShippingOption::class,
            subjectId: $option->id,
            description: sprintf(
                'Shipping option %s for product %s was %s.',
                $option->transport_mode instanceof \BackedEnum
                    ? $option->transport_mode->value
                    : (string) $option->transport_mode,
                $option->product_id,
                $action,
            ),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: [
                'product_id' => $option->product_id,
                'transport_mode' => $option->transport_mode instanceof \BackedEnum
                    ? $option->transport_mode->value
                    : $option->transport_mode,
            ],
            action: $action,
        );
    }
}
