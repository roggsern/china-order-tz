<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CustomerTimelineEvent */
class CustomerTimelineEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type instanceof \BackedEnum
                ? $this->event_type->value
                : $this->event_type,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'title' => $this->title,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurred_at,
            'created_at' => $this->created_at,
        ];
    }
}
