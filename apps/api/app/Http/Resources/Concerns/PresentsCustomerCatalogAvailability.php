<?php

namespace App\Http\Resources\Concerns;

use App\Models\Product;
use App\Services\Catalog\CustomerProductAvailabilityPresenter;

/** @mixin \Illuminate\Http\Resources\Json\JsonResource */
trait PresentsCustomerCatalogAvailability
{
    /**
     * @return array{
     *     is_purchasable: bool,
     *     availability_status: string,
     *     requires_variant_selection: bool,
     *     unavailability_reason?: string
     * }
     */
    protected function customerCatalogAvailability(?Product $product = null): array
    {
        $product ??= $this->resource;

        return app(CustomerProductAvailabilityPresenter::class)->present($product);
    }
}
