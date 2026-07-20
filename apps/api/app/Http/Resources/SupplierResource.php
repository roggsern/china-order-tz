<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Supplier */
class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'slug' => $this->slug,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'payment_terms' => $this->payment_terms,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'supplier_products_count' => $this->whenCounted('supplierProducts'),
            'purchase_orders_count' => $this->whenCounted('purchaseOrders'),
            'supplier_products' => SupplierProductResource::collection($this->whenLoaded('supplierProducts')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
