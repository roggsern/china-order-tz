<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Notification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->event_type
            ?? ($this->type instanceof \BackedEnum ? $this->type->value : $this->type);

        return [
            'id' => $this->id,
            'type' => $type,
            'event_type' => $this->event_type,
            'template_key' => $this->template_key,
            'title' => $this->title,
            'message' => $this->message,
            'channel' => $this->channel instanceof \BackedEnum ? $this->channel->value : $this->channel,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'provider' => $this->provider,
            'data' => $this->data,
            'read_at' => $this->read_at,
            'sent_at' => $this->sent_at,
            'is_read' => $this->isRead(),
            'created_at' => $this->created_at,
        ];
    }
}
