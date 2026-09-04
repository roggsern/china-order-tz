<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Fulfillment;

class FulfillmentAssignedAudit extends BusinessAuditEvent
{
    public static function fromAssignment(
        Fulfillment $fulfillment,
        ?Admin $previous,
        ?Admin $next,
        Admin $actor,
    ): self {
        $fulfillment->loadMissing('order');
        $orderNumber = $fulfillment->order?->order_number;

        return self::make(
            type: ActivityEventType::FulfillmentAssigned,
            actorType: ActivityActorType::Admin,
            actorId: $actor->id,
            subjectType: Fulfillment::class,
            subjectId: $fulfillment->id,
            description: self::describe($previous, $next, $orderNumber),
            oldValues: [
                'assigned_to' => $previous?->id,
                'assigned_name' => $previous?->name,
            ],
            newValues: [
                'assigned_to' => $next?->id,
                'assigned_name' => $next?->name,
            ],
            metadata: [
                'fulfillment_id' => $fulfillment->id,
                'order_id' => $fulfillment->order_id,
                'order_number' => $orderNumber,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'previous_assigned_to' => $previous?->id,
                'previous_assigned_name' => $previous?->name,
                'next_assigned_to' => $next?->id,
                'next_assigned_name' => $next?->name,
            ],
        );
    }

    private static function describe(?Admin $previous, ?Admin $next, ?string $orderNumber): string
    {
        $from = $previous?->name ?? 'Unassigned';
        $to = $next?->name ?? 'Unassigned';
        $order = $orderNumber !== null && $orderNumber !== '' ? " ({$orderNumber})" : '';

        return "Fulfillment assigned{$order}: {$from} → {$to}";
    }
}
