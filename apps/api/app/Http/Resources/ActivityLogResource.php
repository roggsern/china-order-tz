<?php

namespace App\Http\Resources;

use App\Services\Audit\ActivityLogFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ActivityLog */
class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ActivityLogFormatter $formatter */
        $formatter = app(ActivityLogFormatter::class);
        $actor = $this->resolveActor();

        return [
            'id' => $this->id,
            'event_type' => $this->event_type instanceof \BackedEnum
                ? $this->event_type->value
                : $this->event_type,
            'event_type_label' => $this->event_type instanceof \App\Enums\ActivityEventType
                ? $this->event_type->label()
                : null,
            'action' => $this->action,
            'actor_type' => $this->actor_type instanceof \BackedEnum
                ? $this->actor_type->value
                : $this->actor_type,
            'actor_id' => $this->actor_id,
            'actor' => $actor ? [
                'id' => $actor->id,
                'name' => $actor->name ?? null,
                'email' => $actor->email ?? null,
            ] : null,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'description' => $this->description,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'changes' => $formatter->changes($this->old_values, $this->new_values),
            'metadata' => $this->metadata,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at,
        ];
    }
}
