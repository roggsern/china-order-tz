<?php

namespace App\Http\Resources;

use App\Enums\ReviewStatus;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
class AdminReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status ?? ReviewStatus::Pending->value;
        $statusEnum = ReviewStatus::tryFrom($status) ?? ReviewStatus::Pending;

        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'title' => $this->title,
            'comment' => $this->comment ?? $this->body,
            'status' => $status,
            'status_label' => $statusEnum->label(),
            'is_approved' => (bool) $this->is_approved,
            'is_verified_purchase' => (bool) $this->is_verified_purchase,
            'moderation_note' => $this->moderation_note,
            'product_id' => $this->product_id,
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'moderated_at' => $this->moderated_at?->toIso8601String(),
            'customer' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
            ] : null),
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
            ] : null),
            'moderated_by' => $this->whenLoaded('moderatedBy', fn () => $this->moderatedBy ? [
                'id' => $this->moderatedBy->id,
                'name' => $this->moderatedBy->name,
            ] : null),
        ];
    }
}
