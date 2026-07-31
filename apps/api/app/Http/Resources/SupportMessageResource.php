<?php

namespace App\Http\Resources;

use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupportMessage */
class SupportMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'sender_type' => $this->sender_type?->value,
            'sender_id' => $this->sender_id,
            'message' => $this->message,
            'attachments' => $this->attachments ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
