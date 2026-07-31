<?php

namespace App\Services\Catalog;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\PurchasabilityPath;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\StockResolver;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\ProductMedia\VariantMediaResolver;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Collection;

/**
 * Read-only catalog quality aggregator.
 * Reuses purchasability, pricing, stock, and media resolution — does not redefine commerce rules.
 */
class CatalogHealthService
{
    private const SAMPLE_LIMIT = 25;

    public function __construct(
        private readonly ProductPurchasabilityPolicy $purchasability,
        private readonly CommercePricingResolver $pricing,
        private readonly StockResolver $stock,
        private readonly CustomerProductMediaResolver $productMedia,
        private readonly VariantMediaResolver $variantMedia,
    ) {}

    /**
     * @return array{
     *   summary: array{health_score: int, critical_count: int, warning_count: int},
     *   issues: array{
     *     commerce_readiness: array<string, mixed>,
     *     media: array<string, mixed>,
     *     inventory: array<string, mixed>,
     *     catalog_quality: array<string, mixed>
     *   }
     * }
     */
    public function report(): array
    {
        $activeNotPurchasable = [];
        $missingValidPrice = [];
        $activePublicWithoutImages = [];
        $activeMissingInventoryPolicy = [];
        $missingDescriptions = [];
        $variantsWithoutMedia = [];
        $variantsWithoutSku = [];
        $variantsWithoutBarcode = [];
        $variantsMissingValidPrice = [];
        $variantsMissingInventoryPolicy = [];

        Product::query()
            ->where('is_demo', false)
            ->with([
                'variants.prices',
                'variants.inventories',
                'variants.media',
                'variants.product',
                'media' => fn ($query) => $query->images()->active()->ordered(),
                'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
                'inventory',
            ])
            ->orderBy('created_at')
            ->chunkById(100, function (Collection $products) use (
                &$activeNotPurchasable,
                &$missingValidPrice,
                &$activePublicWithoutImages,
                &$activeMissingInventoryPolicy,
                &$missingDescriptions,
                &$variantsWithoutMedia,
                &$variantsWithoutSku,
                &$variantsWithoutBarcode,
                &$variantsMissingValidPrice,
                &$variantsMissingInventoryPolicy,
            ): void {
                foreach ($products as $product) {
                    /** @var Product $product */
                    $isActiveCommerce = $this->isActiveCommerceProduct($product);
                    $isActivePublic = $isActiveCommerce && $this->isPublicVisibility($product);

                    if ($isActiveCommerce) {
                        $evaluation = $this->purchasability->evaluate($product);
                        if (! $evaluation->isPurchasable) {
                            $activeNotPurchasable[] = $product->id;
                        }

                        if (! $this->productHasValidPrice($product)) {
                            $missingValidPrice[] = $product->id;
                        }

                        if ($this->activeProductMissingInventoryPolicy($product)) {
                            $activeMissingInventoryPolicy[] = $product->id;
                        }
                    }

                    if ($isActivePublic && $this->productMedia->resolvePrimary($product) === null) {
                        $activePublicWithoutImages[] = $product->id;
                    }

                    if ($this->descriptionMissing($product)) {
                        $missingDescriptions[] = $product->id;
                    }

                    foreach ($product->variants as $variant) {
                        /** @var ProductVariant $variant */
                        if (! $variant->is_active) {
                            continue;
                        }

                        if ($this->variantLacksOwnMedia($product, $variant)) {
                            $variantsWithoutMedia[] = $variant->id;
                        }

                        if ($this->blankString($variant->sku)) {
                            $variantsWithoutSku[] = $variant->id;
                        }

                        if ($this->blankString($variant->barcode)) {
                            $variantsWithoutBarcode[] = $variant->id;
                        }

                        if ($this->variantMissingValidPrice($variant, $product)) {
                            $variantsMissingValidPrice[] = $variant->id;
                        }

                        if (! $this->stock->hasVariantInventoryPolicy($variant)) {
                            $variantsMissingInventoryPolicy[] = $variant->id;
                        }
                    }
                }
            });

        $commerce = [
            'active_not_purchasable' => $this->issue(
                severity: 'critical',
                priority: 'P0',
                count: count($activeNotPurchasable),
                ids: $activeNotPurchasable,
                idKey: 'product_ids',
            ),
            'missing_valid_price' => $this->issue(
                severity: 'critical',
                priority: 'P0',
                count: count($missingValidPrice),
                ids: $missingValidPrice,
                idKey: 'product_ids',
            ),
            'variants_missing_valid_price' => $this->issue(
                severity: 'critical',
                priority: 'P0',
                count: count($variantsMissingValidPrice),
                ids: $variantsMissingValidPrice,
                idKey: 'variant_ids',
            ),
        ];

        $media = [
            'active_public_without_images' => $this->issue(
                severity: 'critical',
                priority: 'P0',
                count: count($activePublicWithoutImages),
                ids: $activePublicWithoutImages,
                idKey: 'product_ids',
            ),
            'variants_without_media' => $this->issue(
                severity: 'warning',
                priority: 'P1',
                count: count($variantsWithoutMedia),
                ids: $variantsWithoutMedia,
                idKey: 'variant_ids',
            ),
        ];

        $inventory = [
            'active_missing_inventory_policy' => $this->issue(
                severity: 'warning',
                priority: 'P1',
                count: count($activeMissingInventoryPolicy),
                ids: $activeMissingInventoryPolicy,
                idKey: 'product_ids',
            ),
            'variants_missing_inventory_policy' => $this->issue(
                severity: 'warning',
                priority: 'P1',
                count: count($variantsMissingInventoryPolicy),
                ids: $variantsMissingInventoryPolicy,
                idKey: 'variant_ids',
            ),
        ];

        $catalogQuality = [
            'variants_without_sku' => $this->issue(
                severity: 'warning',
                priority: 'P1',
                count: count($variantsWithoutSku),
                ids: $variantsWithoutSku,
                idKey: 'variant_ids',
            ),
            'variants_without_barcode' => $this->issue(
                severity: 'warning',
                priority: 'P1',
                count: count($variantsWithoutBarcode),
                ids: $variantsWithoutBarcode,
                idKey: 'variant_ids',
            ),
            'missing_descriptions' => $this->issue(
                severity: 'info',
                priority: 'P2',
                count: count($missingDescriptions),
                ids: $missingDescriptions,
                idKey: 'product_ids',
            ),
        ];

        $criticalCount = $commerce['active_not_purchasable']['count']
            + $commerce['missing_valid_price']['count']
            + $commerce['variants_missing_valid_price']['count']
            + $media['active_public_without_images']['count'];

        $warningCount = $media['variants_without_media']['count']
            + $inventory['active_missing_inventory_policy']['count']
            + $inventory['variants_missing_inventory_policy']['count']
            + $catalogQuality['variants_without_sku']['count']
            + $catalogQuality['variants_without_barcode']['count'];

        return [
            'summary' => [
                'health_score' => $this->computeHealthScore($criticalCount, $warningCount, $catalogQuality['missing_descriptions']['count']),
                'critical_count' => $criticalCount,
                'warning_count' => $warningCount,
            ],
            'issues' => [
                'commerce_readiness' => $commerce,
                'media' => $media,
                'inventory' => $inventory,
                'catalog_quality' => $catalogQuality,
            ],
        ];
    }

    private function isActiveCommerceProduct(Product $product): bool
    {
        if (! $product->is_active) {
            return false;
        }

        $lifecycle = $product->lifecycle_status;
        if ($lifecycle instanceof ProductLifecycleStatus) {
            return $lifecycle->isPurchasable();
        }

        return ProductLifecycleStatus::tryFromMixed($lifecycle)?->isPurchasable() ?? false;
    }

    private function isPublicVisibility(Product $product): bool
    {
        $visibility = $product->visibility;
        if (! $visibility instanceof ProductVisibility) {
            $visibility = ProductVisibility::tryFromMixed($visibility) ?? ProductVisibility::Hidden;
        }

        return $visibility->isStorefrontVisible();
    }

    /**
     * Price validity via CommercePricingResolver + purchasability path selection.
     */
    private function productHasValidPrice(Product $product): bool
    {
        $path = $this->purchasability->resolvePath($product);

        if ($path === PurchasabilityPath::Variant) {
            foreach ($product->variants as $variant) {
                if (! $variant->is_active) {
                    continue;
                }

                if (! $this->variantMissingValidPrice($variant, $product)) {
                    return true;
                }
            }

            return false;
        }

        $result = $this->pricing->resolveSimpleProductPrice($product);

        return $result->resolved && (float) $result->unitPrice > 0;
    }

    private function variantMissingValidPrice(ProductVariant $variant, Product $product): bool
    {
        $result = $this->pricing->resolveVariantProductPrice($variant, null, $product);

        return ! ($result->resolved && (float) $result->unitPrice > 0);
    }

    private function activeProductMissingInventoryPolicy(Product $product): bool
    {
        $path = $this->purchasability->resolvePath($product);

        if ($path === PurchasabilityPath::Variant) {
            if ($product->variants->where('is_active', true)->isEmpty()) {
                return true;
            }

            foreach ($product->variants as $variant) {
                if (! $variant->is_active) {
                    continue;
                }

                if (! $this->stock->hasVariantInventoryPolicy($variant)) {
                    return true;
                }
            }

            return false;
        }

        return ! $this->stock->hasSimpleInventoryPolicy($product);
    }

    private function descriptionMissing(Product $product): bool
    {
        return $this->blankString($product->description)
            && $this->blankString($product->short_description);
    }

    /**
     * Variant lacks bound media (product-level fallback does not count as variant media).
     * Uses VariantMediaResolver: own media wins; identical-to-product-level resolution means fallback only.
     */
    private function variantLacksOwnMedia(Product $product, ProductVariant $variant): bool
    {
        $resolved = $this->variantMedia->resolve($product, $variant);
        $productLevel = $this->variantMedia->resolve($product, null);

        if ($resolved->isEmpty()) {
            return true;
        }

        return $resolved->pluck('id')->sort()->values()->all()
            === $productLevel->pluck('id')->sort()->values()->all();
    }

    private function blankString(mixed $value): bool
    {
        return trim((string) ($value ?? '')) === '';
    }

    /**
     * @param  list<string>  $ids
     * @return array{severity: string, priority: string, count: int, product_ids?: list<string>, variant_ids?: list<string>}
     */
    private function issue(
        string $severity,
        string $priority,
        int $count,
        array $ids,
        string $idKey,
    ): array {
        return [
            'severity' => $severity,
            'priority' => $priority,
            'count' => $count,
            $idKey => array_values(array_slice($ids, 0, self::SAMPLE_LIMIT)),
        ];
    }

    private function computeHealthScore(int $criticalCount, int $warningCount, int $infoCount): int
    {
        $score = 100;
        $score -= min(70, $criticalCount * 10);
        $score -= min(25, $warningCount * 3);
        $score -= min(5, $infoCount * 1);

        return max(0, $score);
    }
}
