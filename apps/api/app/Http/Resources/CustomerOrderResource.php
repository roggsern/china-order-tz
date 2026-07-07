<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class CustomerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'source' => $this->resolveSource(),
            'status' => $this->status->value,
            'total' => $this->total,
            'created_at' => $this->created_at,
        ];
    }
}
