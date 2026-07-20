<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Product;

class ProductUpdated extends BusinessAuditEvent
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function fromChanges(
        Product $product,
        array $oldValues,
        array $newValues,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::ProductUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Product::class,
            subjectId: $product->id,
            description: sprintf('Product "%s" was updated.', $product->name),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: ['sku' => $product->sku],
        );
    }
}
