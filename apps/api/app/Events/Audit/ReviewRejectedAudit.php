<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Enums\ReviewStatus;
use App\Models\Admin;
use App\Models\Review;

class ReviewRejectedAudit extends BusinessAuditEvent
{
    public static function fromReview(Review $review, Admin $admin, ?string $note = null, ?string $previousStatus = null): self
    {
        return self::make(
            type: ActivityEventType::ReviewRejected,
            actorType: ActivityActorType::Admin,
            actorId: $admin->id,
            subjectType: Review::class,
            subjectId: $review->id,
            description: sprintf('Product review for "%s" was rejected.', $review->product?->name ?? $review->product_id),
            oldValues: ['status' => $previousStatus ?? ReviewStatus::Pending->value],
            newValues: ['status' => $review->status, 'is_approved' => false],
            metadata: array_filter([
                'product_id' => $review->product_id,
                'user_id' => $review->user_id,
                'moderation_note' => $note,
            ]),
        );
    }
}
