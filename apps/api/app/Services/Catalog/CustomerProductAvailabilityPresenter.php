<?php

namespace App\Services\Catalog;

use App\Enums\CustomerProductAvailabilityStatus;
use App\Enums\CustomerProductUnavailabilityReason;
use App\Enums\PurchasabilityPath;
use App\Models\Product;
use App\Services\Inventory\CatalogStockPresenter;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;

/**
 * Customer-facing purchasability vs stock availability (ADR 053 / ADMIN-12.12G).
 */
final class CustomerProductAvailabilityPresenter
{
    public function __construct(
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
        private readonly CatalogStockPresenter $stockPresenter,
    ) {}

    /**
     * @return array{
     *     is_purchasable: bool,
     *     availability_status: string,
     *     requires_variant_selection: bool,
     *     unavailability_reason?: string
     * }
     */
    public function present(Product $product): array
    {
        $evaluation = $this->purchasabilityPolicy->evaluate($product);
        $requiresVariantSelection = $evaluation->path === PurchasabilityPath::Variant;

        if (! $evaluation->isPurchasable) {
            $payload = [
                'is_purchasable' => false,
                'availability_status' => CustomerProductAvailabilityStatus::Unavailable->value,
                'requires_variant_selection' => $requiresVariantSelection,
            ];

            $reason = $this->resolveUnavailabilityReason($evaluation->errors);
            if ($reason !== null) {
                $payload['unavailability_reason'] = $reason;
            }

            return $payload;
        }

        if ($this->resolveAvailableQuantity($product, $evaluation->path) <= 0) {
            return [
                'is_purchasable' => true,
                'availability_status' => CustomerProductAvailabilityStatus::OutOfStock->value,
                'requires_variant_selection' => $requiresVariantSelection,
            ];
        }

        return [
            'is_purchasable' => true,
            'availability_status' => CustomerProductAvailabilityStatus::Available->value,
            'requires_variant_selection' => $requiresVariantSelection,
        ];
    }

    /**
     * @param  list<string>  $errors
     */
    private function resolveUnavailabilityReason(array $errors): ?string
    {
        foreach ($errors as $error) {
            $reason = match (true) {
                str_contains($error, 'inventory policy') => CustomerProductUnavailabilityReason::MissingInventoryPolicy,
                str_contains($error, 'base price') => CustomerProductUnavailabilityReason::InvalidPricing,
                str_contains($error, 'sellable variant') => CustomerProductUnavailabilityReason::MissingSellableVariant,
                str_contains($error, 'lifecycle') => CustomerProductUnavailabilityReason::LifecycleInactive,
                str_contains($error, 'shipping option') => CustomerProductUnavailabilityReason::MissingShippingOptions,
                str_contains($error, 'No purchasable path') => CustomerProductUnavailabilityReason::NoPurchasablePath,
                default => null,
            };

            if ($reason !== null) {
                return $reason->value;
            }
        }

        return $errors !== []
            ? CustomerProductUnavailabilityReason::Unavailable->value
            : null;
    }

    private function resolveAvailableQuantity(Product $product, PurchasabilityPath $path): int
    {
        if ($path === PurchasabilityPath::Variant) {
            $total = 0;

            foreach ($this->purchasabilityPolicy->sellableVariants($product) as $variant) {
                $total += $this->stockPresenter->availableForVariant($variant, $product);
            }

            return $total;
        }

        if ($path === PurchasabilityPath::Simple) {
            return $this->stockPresenter->availableForSimple($product);
        }

        return 0;
    }
}
