<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DeliveryAddress */
class DeliveryAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city,
            'district' => $this->district,
            'street' => $this->street,
            'landmark' => $this->landmark,
            'postal_code' => $this->postal_code,
            'updated_at' => $this->updated_at,
        ];
    }
}
