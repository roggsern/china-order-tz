<?php

namespace App\Services\Inventory;

use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\Catalog\GenerateVariantSku;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\Stores\StoreService;

/**
 * TZ_LOCAL inventory scoping — stock is isolated per store location (ROVI ≠ ZION).
 * China Import continues to use MAIN / commercial stock paths.
 */
final class TzLocalInventoryScope
{
    public function __construct(
        private readonly CommerceChannelResolver $commerceChannels,
        private readonly StoreService $stores,
        private readonly GenerateVariantSku $generateVariantSku,
    ) {}

    public function appliesTo(Product $product): bool
    {
        return $this->commerceChannels->isTzLocalProduct($product);
    }

    public function storeFor(Product $product): ?Store
    {
        if (! filled($product->store_id)) {
            return null;
        }

        if ($product->relationLoaded('store') && $product->store !== null) {
            return $product->store;
        }

        return Store::query()->find($product->store_id);
    }

    public function storeLocation(Product $product): ?InventoryLocation
    {
        $store = $this->storeFor($product);
        if ($store === null) {
            return null;
        }

        return $this->stores->defaultLocation($store);
    }

    public function warehouseCodeFor(Product $product): ?string
    {
        $location = $this->storeLocation($product);

        return $location !== null ? strtoupper((string) $location->code) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyVariantInventoryDefaults(Product $product, array $data): array
    {
        if (! $this->appliesTo($product)) {
            return $data;
        }

        $location = $this->storeLocation($product);
        if ($location === null) {
            return $data;
        }

        $requestedWarehouse = array_key_exists('warehouse_code', $data)
            ? strtoupper(trim((string) $data['warehouse_code']))
            : null;

        if ($requestedWarehouse === null || $requestedWarehouse === 'MAIN') {
            $data['warehouse_code'] = strtoupper((string) $location->code);
        }

        if (! array_key_exists('inventory_location_id', $data) || blank($data['inventory_location_id'])) {
            $data['inventory_location_id'] = $location->id;
        }

        return $data;
    }

    public function isStoreCommerceWarehouse(Product $product, string $warehouseCode): bool
    {
        if (! $this->appliesTo($product)) {
            return false;
        }

        $storeCode = $this->warehouseCodeFor($product);

        return $storeCode !== null && strtoupper($warehouseCode) === $storeCode;
    }

    /**
     * Default variant for POS simple-product selling (no customer-facing variant UX).
     */
    public function ensurePosDefaultVariant(Product $product): ProductVariant
    {
        $existing = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $sku = filled($product->sku)
            ? (string) $product->sku
            : $this->generateVariantSku->handle($product, []);

        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $sku,
            'price' => null,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 0,
        ]);
    }

    public function resolveCommerceWarehouse(Product $product, string $contextWarehouse): string
    {
        if ($this->appliesTo($product) && strtoupper($contextWarehouse) === 'MAIN') {
            return $this->warehouseCodeFor($product) ?? 'MAIN';
        }

        return strtoupper($contextWarehouse);
    }
}
