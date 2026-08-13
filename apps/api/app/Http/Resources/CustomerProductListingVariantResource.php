<?php

namespace App\Http\Resources;

use App\Services\Inventory\CatalogStockPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Listing-card variant summary — stock signals only.
 * Omits attributes, media galleries, and price graphs (those belong on PDP).
 *
 * @mixin \App\Models\ProductVariant
 */
class CustomerProductListingVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $presenter = app(CatalogStockPresenter::class);
        $product = $this->relationLoaded('product') ? $this->product : null;
        $stock = $product !== null
            ? $presenter->resolveForProduct($product, $this->resource)
            : app(\App\Services\Inventory\StockResolver::class)->resolveVariantProduct($this->resource);

        $available = max(0, $stock->quantityAvailable);
        $includeStock = $this->relationLoaded('inventory')
            || $this->relationLoaded('inventories')
            || $this->relationLoaded('chinaCommercialStock')
            || $stock->resolved;

        $inventoryContract = $includeStock
            ? $presenter->toInventoryContract($stock, includeWarehouseLocation: false)
            : null;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'inventory' => $this->when($includeStock && $inventoryContract !== null, fn () => [
                'available_quantity' => $inventoryContract['available_quantity'] ?? $available,
            ]),
            'stock' => $this->when($includeStock, fn () => $available),
            'in_stock' => $this->when($includeStock, fn () => $available > 0),
        ];
    }
}
