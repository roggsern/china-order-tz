<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
class CustomerProductReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'title' => $this->title,
            'comment' => $this->comment ?? $this->body,
            'author' => $this->whenLoaded('user', fn () => $this->user?->name ?? 'Customer'),
            'verified' => (bool) $this->is_verified_purchase,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
