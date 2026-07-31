<?php

namespace App\Http\Resources;

use App\Models\ChinaProcurementRequirement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChinaProcurementRequirement */
class ChinaProcurementRequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'supplier_id' => $this->supplier_id,
            'quantity_required' => (int) $this->quantity_required,
            'quantity_purchased' => (int) $this->quantity_purchased,
            'quantity_remaining' => $this->remainingQuantity(),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof \BackedEnum ? $this->status->label() : null,
            'variant_attributes' => $this->variant_attributes ?? [],
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'slug' => $this->product?->slug,
                'category' => $this->product?->relationLoaded('category') && $this->product?->category
                    ? [
                        'id' => $this->product->category->id,
                        'name' => $this->product->category->name,
                        'slug' => $this->product->category->slug,
                    ]
                    : null,
            ]),
            'variant' => $this->whenLoaded('variant', fn () => [
                'id' => $this->variant?->id,
                'sku' => $this->variant?->sku,
                'name' => $this->variant?->name,
            ]),
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
                'code' => $this->supplier?->code,
            ]),
            'linked_orders' => $this->whenLoaded('links', function () {
                return $this->links
                    ->groupBy('order_id')
                    ->map(function ($links) {
                        $order = $links->first()?->order;

                        return [
                            'order_id' => $order?->id,
                            'order_number' => $order?->order_number,
                            'placed_at' => $order?->placed_at?->toIso8601String(),
                            'quantity' => $links->sum('quantity'),
                        ];
                    })
                    ->values()
                    ->all();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
