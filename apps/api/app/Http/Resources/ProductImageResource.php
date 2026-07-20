<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\ProductImage */
class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'url' => $this->path
                ? Storage::disk('public')->url($this->path)
                : null,
            'alt_text' => $this->alt_text,
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
        ];
    }
}
