<?php

namespace App\Http\Resources\Concerns;

use App\Enums\PurchasabilityPath;
use App\Models\Product;
use App\Services\Inventory\CatalogStockPresenter;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;

/** @mixin \Illuminate\Http\Resources\Json\JsonResource */
trait PresentsCustomerCatalogStock
{
    protected function usesSimpleProductStockPath(?Product $product = null): bool
    {
        $product ??= $this->resource;

        return app(ProductPurchasabilityPolicy::class)->resolvePath($product) === PurchasabilityPath::Simple;
    }

    protected function simpleProductAvailableStock(?Product $product = null): int
    {
        $product ??= $this->resource;

        return app(CatalogStockPresenter::class)->availableForSimple($product);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function simpleProductInventoryContract(?Product $product = null): ?array
    {
        $product ??= $this->resource;
        $presenter = app(CatalogStockPresenter::class);
        $stock = $presenter->resolveForProduct($product);

        if (! $stock->resolved) {
            return null;
        }

        return $presenter->toInventoryContract($stock, includeWarehouseLocation: false);
    }
}
