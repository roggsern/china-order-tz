<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DeliveryOption */
class DeliveryOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'delivery_type' => $this->delivery_type?->value ?? $this->delivery_type,
            'delivery_type_label' => $this->delivery_type?->label(),
            'shipping_method' => $this->shipping_method?->value ?? $this->shipping_method,
            'shipping_method_label' => $this->shipping_method?->label(),
            'delivery_status' => $this->delivery_status?->value ?? $this->delivery_status,
            'delivery_status_label' => $this->delivery_status?->label(),
            'agent_name' => $this->agent_name,
            'agent_contact' => $this->agent_contact,
            'notes' => $this->notes,
            'confirmed_at' => $this->confirmed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
