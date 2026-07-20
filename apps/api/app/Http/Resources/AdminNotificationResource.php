<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Notification */
class AdminNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'admin_id' => $this->admin_id,
            'event_type' => $this->event_type,
            'template_key' => $this->template_key,
            'title' => $this->title,
            'message' => $this->message,
            'channel' => $this->channel instanceof \BackedEnum ? $this->channel->value : $this->channel,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'provider' => $this->provider,
            'provider_message_id' => $this->provider_message_id,
            'error_message' => $this->error_message,
            'data' => $this->data,
            'sent_at' => $this->sent_at,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ] : null),
        ];
    }
}
