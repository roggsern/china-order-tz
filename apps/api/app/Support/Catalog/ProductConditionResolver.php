<?php

namespace App\Support\Catalog;

use App\Enums\ProductCondition;
use App\Models\CatalogProductType;
use App\Models\Product;
use Database\Support\CatalogAttributeDomainMap;

/**
 * Product-level condition only — never a catalog/variant attribute.
 */
final class ProductConditionResolver
{
    public static function isEligible(?CatalogProductType $type): bool
    {
        if ($type === null || blank($type->name)) {
            return false;
        }

        return CatalogAttributeDomainMap::supportsProductCondition((string) $type->name);
    }

    /**
     * Resolve the value to persist.
     * Non-eligible types always store null (submitted values ignored).
     * Eligible types default to BRAND_NEW when omitted.
     */
    public static function resolveForPersist(
        mixed $submitted,
        ?CatalogProductType $type,
    ): ?ProductCondition {
        if (! self::isEligible($type)) {
            return null;
        }

        if ($submitted === null || $submitted === '') {
            return ProductCondition::BrandNew;
        }

        if ($submitted instanceof ProductCondition) {
            return $submitted;
        }

        return ProductCondition::tryFrom((string) $submitted) ?? ProductCondition::BrandNew;
    }

    /**
     * Read-side effective condition (never writes the DB).
     * Eligible + stored null → BRAND_NEW. Non-eligible + null → null.
     */
    public static function effective(
        ?ProductCondition $stored,
        ?CatalogProductType $type,
    ): ?ProductCondition {
        if ($stored !== null) {
            return $stored;
        }

        if (! self::isEligible($type)) {
            return null;
        }

        return ProductCondition::BrandNew;
    }

    /**
     * Effective condition for a product model (loads catalog type if needed).
     */
    public static function effectiveForProduct(Product $product): ?ProductCondition
    {
        $product->loadMissing('catalogProductType');

        return self::effective($product->product_condition, $product->catalogProductType);
    }

    public static function label(?ProductCondition $condition): ?string
    {
        return $condition?->label();
    }
}
