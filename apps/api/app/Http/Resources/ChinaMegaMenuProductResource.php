<?php

namespace App\Http\Resources;

use App\Services\Catalog\CustomerProductMediaResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mega-menu featured tile — identity + primary image + brand name only.
 * Intentionally omits variants, inventory, shipping, reviews, and long copy.
 *
 * @mixin \App\Models\Product
 */
class ChinaMegaMenuProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primary = app(CustomerProductMediaResolver::class)->resolvePrimary($this->resource);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'primary_image' => $primary === null ? null : [
                'id' => $primary['id'] ?? null,
                'path' => $primary['path'] ?? null,
                'url' => $primary['url'] ?? null,
                'alt_text' => $primary['alt_text'] ?? null,
            ],
            'brand' => $this->when(
                $this->relationLoaded('brand') && $this->brand !== null,
                fn () => [
                    'id' => $this->brand->id,
                    'name' => $this->brand->name,
                    'slug' => $this->brand->slug,
                ],
            ),
        ];
    }
}
