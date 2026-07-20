<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\Product;

class ProductCreated extends BusinessAuditEvent
{
    public static function fromProduct(Product $product, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::ProductCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Product::class,
            subjectId: $product->id,
            description: sprintf('Product "%s" was created.', $product->name),
            newValues: [
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => $product->price,
                'lifecycle_status' => $product->lifecycle_status instanceof \BackedEnum
                    ? $product->lifecycle_status->value
                    : $product->lifecycle_status,
            ],
            metadata: [
                'slug' => $product->slug,
                'category_id' => $product->category_id,
            ],
        );
    }
}
