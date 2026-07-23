<?php

namespace App\Services\ProductPurchasability;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\PurchasabilityPath;
use App\Enums\VariantPriceType;
use App\Models\CatalogProductType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\StockResolver;
use App\Support\Catalog\CatalogLeafCategoryRules;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Single Product Purchasability policy (ADR 053 / Phase 2A-1).
 *
 * Visibility (catalog display) is separate from purchasability (add-to-cart).
 * Path selection: sellable variants → Variant path; else → Simple (base) path.
 * Inventory policy presence reads via StockResolver (ADR 055).
 */
final class ProductPurchasabilityPolicy
{
    public function __construct(
        private readonly StockResolver $stockResolver,
    ) {}

    public function evaluate(Product $product): ProductPurchasabilityResult
    {
        if ($product->is_demo) {
            return new ProductPurchasabilityResult(
                path: PurchasabilityPath::None,
                isPurchasable: false,
                isVisible: false,
                errors: ['Product is a demo record.'],
            );
        }

        $errors = [];
        $visible = $this->isVisible($product);

        if (! $this->hasActiveLifecycle($product)) {
            $errors[] = 'Product lifecycle must be active.';
        }

        $path = $this->resolvePath($product);

        if ($path === PurchasabilityPath::Variant) {
            $pathErrors = $this->validateVariantPath($product);
        } elseif ($path === PurchasabilityPath::Simple) {
            $pathErrors = $this->validateSimplePath($product);
        } else {
            $pathErrors = ['No purchasable path resolved.'];
        }

        $errors = array_values(array_unique([...$errors, ...$pathErrors]));

        return new ProductPurchasabilityResult(
            path: $path,
            isPurchasable: $errors === [],
            isVisible: $visible,
            errors: $errors,
        );
    }

    public function isPurchasable(Product $product): bool
    {
        return $this->evaluate($product)->isPurchasable;
    }

    /**
     * Catalog display — independent of add-to-cart purchasability.
     */
    public function isVisible(Product $product): bool
    {
        if ($product->is_demo) {
            return false;
        }

        if (! $product->is_active) {
            return false;
        }

        $lifecycle = $product->lifecycle_status;
        if (! $lifecycle instanceof ProductLifecycleStatus || ! $lifecycle->isListed()) {
            return false;
        }

        $visibility = $product->visibility;
        if (! $visibility instanceof ProductVisibility) {
            $visibility = ProductVisibility::tryFromMixed($visibility) ?? ProductVisibility::Hidden;
        }

        return $visibility->isStorefrontVisible();
    }

    /**
     * IF sellable variants exist → Variant; ELSE → Simple.
     */
    public function resolvePath(Product $product): PurchasabilityPath
    {
        if ($this->hasSellableVariants($product)) {
            return PurchasabilityPath::Variant;
        }

        return PurchasabilityPath::Simple;
    }

    public function hasSellableVariants(Product $product): bool
    {
        return $this->sellableVariants($product)->isNotEmpty();
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function sellableVariants(Product $product): Collection
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->with(['prices', 'inventories'])->get();

        return $variants
            ->filter(fn (ProductVariant $variant) => $this->isSellableVariant($variant))
            ->values();
    }

    public function isSellableVariant(ProductVariant $variant): bool
    {
        if (! $variant->is_active) {
            return false;
        }

        if (! $this->variantHasValidRetailPrice($variant)) {
            return false;
        }

        return $this->stockResolver->hasVariantInventoryPolicy($variant);
    }

    /**
     * Lifecycle eligibility for commerce (demo + active lifecycle). Path/pricing are separate.
     */
    public function isLifecycleEligible(Product $product): bool
    {
        if ($product->is_demo) {
            return false;
        }

        return $this->hasActiveLifecycle($product);
    }

    /**
     * Publish gate when activating a product (ADR 053 §E).
     *
     * @throws ValidationException
     */
    public function assertPublishable(Product $product): void
    {
        $messages = [];

        if ($product->is_demo) {
            $messages['is_demo'] = ['Demo products cannot be published.'];
        }

        if (! $this->hasActiveLifecycle($product)) {
            $messages['lifecycle_status'] = ['Published products must have an active lifecycle.'];
        }

        $this->collectLeafCategoryErrors($product, $messages);
        $this->collectCatalogProductTypeErrors($product, $messages);

        $path = $this->resolvePath($product);
        $pathErrors = $path === PurchasabilityPath::Variant
            ? $this->validateVariantPath($product)
            : $this->validateSimplePath($product);

        if ($pathErrors !== []) {
            $field = $path === PurchasabilityPath::Variant ? 'variants' : 'price';
            $messages[$field] = $pathErrors;
        }

        if ($path === PurchasabilityPath::None) {
            $messages['purchasability'] = ['Published products must have exactly one purchasable path (Simple or Variant).'];
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * @return list<string>
     */
    private function validateSimplePath(Product $product): array
    {
        $errors = [];

        if (! $this->hasValidBasePrice($product)) {
            $errors[] = 'Simple products require a valid base price greater than zero.';
        }

        if (! $this->stockResolver->hasSimpleInventoryPolicy($product)) {
            $errors[] = 'Simple products require a product-level inventory policy.';
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateVariantPath(Product $product): array
    {
        if ($this->sellableVariants($product)->isEmpty()) {
            return ['Variant products require at least one sellable variant with pricing and inventory.'];
        }

        return [];
    }

    private function hasActiveLifecycle(Product $product): bool
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

    private function hasValidBasePrice(Product $product): bool
    {
        return (float) $product->price > 0;
    }

    private function variantHasValidRetailPrice(ProductVariant $variant): bool
    {
        $prices = $variant->relationLoaded('prices')
            ? $variant->prices
            : $variant->prices()->get();

        foreach ($prices as $price) {
            $type = $price->price_type instanceof VariantPriceType
                ? $price->price_type
                : VariantPriceType::tryFrom((string) $price->price_type);

            if ($type !== VariantPriceType::Retail) {
                continue;
            }

            if (! $price->isCurrentlyActive()) {
                continue;
            }

            if ((float) $price->amount > 0) {
                return true;
            }
        }

        if ($variant->price !== null && (float) $variant->price > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, list<string>>  $messages
     */
    private function collectLeafCategoryErrors(Product $product, array &$messages): void
    {
        $categoryId = $product->category_id;

        if (! filled($categoryId)) {
            $messages['category_id'] = ['Published products require a valid leaf category.'];

            return;
        }

        try {
            CatalogLeafCategoryRules::assertValidLeafParent((string) $categoryId, 'category_id');
        } catch (ValidationException $e) {
            $messages = array_merge($messages, $e->errors());
        }
    }

    /**
     * @param  array<string, list<string>>  $messages
     */
    private function collectCatalogProductTypeErrors(Product $product, array &$messages): void
    {
        $cptId = $product->catalog_product_type_id;

        if (! filled($cptId)) {
            $messages['catalog_product_type_id'] = ['Published products require a valid Catalog Product Type.'];

            return;
        }

        $cpt = $product->relationLoaded('catalogProductType')
            ? $product->catalogProductType
            : CatalogProductType::query()->find($cptId);

        if ($cpt === null) {
            $messages['catalog_product_type_id'] = ['The selected Catalog Product Type is invalid.'];

            return;
        }

        if (! $cpt->is_active) {
            $messages['catalog_product_type_id'] = ['The Catalog Product Type must be active.'];

            return;
        }

        if ((string) $cpt->subcategory_id !== (string) $product->category_id) {
            $messages['catalog_product_type_id'] = [
                'Catalog Product Type parent category must match the product category.',
            ];
        }
    }
}
