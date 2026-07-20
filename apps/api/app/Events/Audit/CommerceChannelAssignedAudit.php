<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\Product;

class CommerceChannelAssignedAudit extends BusinessAuditEvent
{
    public static function fromAssignment(
        Product $product,
        CommerceChannel $channel,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::CommerceChannelAssigned,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Product::class,
            subjectId: $product->id,
            description: sprintf(
                'Commerce channel %s assigned to product %s.',
                $channel->code,
                $product->sku ?: $product->name,
            ),
            newValues: [
                'commerce_channel_id' => $channel->id,
                'commerce_channel_code' => $channel->code,
            ],
            metadata: [
                'product_id' => $product->id,
                'channel_name' => $channel->name,
            ],
        );
    }
}
