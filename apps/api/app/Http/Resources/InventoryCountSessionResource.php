<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InventoryCountSession */
class InventoryCountSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'count_number' => $this->count_number,
            'store_id' => $this->store_id,
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store?->id,
                'code' => $this->store?->code,
                'name' => $this->store?->name,
            ]),
            'scope' => $this->scope instanceof \BackedEnum ? $this->scope->value : $this->scope,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'category_id' => $this->category_id,
            'notes' => $this->notes,
            'started_at' => $this->started_at,
            'submitted_at' => $this->submitted_at,
            'approved_at' => $this->approved_at,
            'lines' => InventoryCountLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at,
        ];
    }
}
