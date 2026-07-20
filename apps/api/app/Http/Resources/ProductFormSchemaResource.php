<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Metadata-driven product form schema consumed by Admin, storefront, and POS.
 *
 * @property array{product_type: mixed, attributes: mixed, dependencies: mixed, capabilities: mixed} $resource
 */
class ProductFormSchemaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->resource['product_type'] ?? null;

        return [
            'product_type' => $type === null ? null : [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'description' => $type->description,
                'sku_pattern' => $type->sku_pattern,
            ],
            'attributes' => $this->resource['attributes'] ?? [],
            'dependencies' => $this->resource['dependencies'] ?? [],
            'capabilities' => $this->resource['capabilities'] ?? [
                'has_configurations' => false,
                'allows_price_override' => false,
                'allows_moq_pricing' => false,
            ],
        ];
    }
}
