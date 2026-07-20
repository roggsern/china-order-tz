<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\InventoryCountSession;
use App\Models\InventoryStockMovement;

class InventoryControlAudit extends BusinessAuditEvent
{
    public static function received(InventoryStockMovement $movement, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::InventoryReceived,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: InventoryStockMovement::class,
            subjectId: $movement->id,
            description: sprintf('Inventory received: %+d units', $movement->quantity_change),
            newValues: [
                'product_variant_id' => $movement->product_variant_id,
                'store_id' => $movement->store_id,
                'quantity_change' => $movement->quantity_change,
                'quantity_after' => $movement->quantity_after,
                'reason' => $movement->reason,
            ],
        );
    }

    public static function adjusted(InventoryStockMovement $movement, Admin $admin): self
    {
        return self::make(
            type: ActivityEventType::InventoryAdjusted,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: InventoryStockMovement::class,
            subjectId: $movement->id,
            description: sprintf('Inventory adjusted: %+d (%s)', $movement->quantity_change, $movement->reason),
            newValues: [
                'product_variant_id' => $movement->product_variant_id,
                'store_id' => $movement->store_id,
                'quantity_change' => $movement->quantity_change,
                'quantity_after' => $movement->quantity_after,
                'reason' => $movement->reason,
                'movement_type' => $movement->movement_type instanceof \BackedEnum
                    ? $movement->movement_type->value
                    : $movement->movement_type,
            ],
        );
    }

    public static function damaged(InventoryStockMovement $movement, Admin $admin): self
    {
        return self::make(
            type: ActivityEventType::DamagedStockRecorded,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: InventoryStockMovement::class,
            subjectId: $movement->id,
            description: sprintf('Damaged stock recorded: %d units', abs((int) $movement->quantity_change)),
            newValues: [
                'product_variant_id' => $movement->product_variant_id,
                'store_id' => $movement->store_id,
                'quantity_change' => $movement->quantity_change,
                'damaged_after' => $movement->damaged_after,
                'reason' => $movement->reason,
            ],
        );
    }

    public static function countCompleted(InventoryCountSession $session, Admin $admin): self
    {
        return self::make(
            type: ActivityEventType::StockCountCompleted,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: InventoryCountSession::class,
            subjectId: $session->id,
            description: 'Stock count approved: '.$session->count_number,
            newValues: [
                'store_id' => $session->store_id,
                'count_number' => $session->count_number,
                'lines' => $session->lines->count(),
            ],
        );
    }
}
