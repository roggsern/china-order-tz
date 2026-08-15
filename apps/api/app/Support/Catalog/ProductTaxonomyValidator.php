<?php

namespace App\Support\Catalog;

use App\Enums\CatalogOrigin;
use App\Enums\CommerceChannelCode;
use App\Models\Category;
use Illuminate\Validation\ValidationException;

final class ProductTaxonomyValidator
{
    /**
     * Channel/origin isolation plus leaf classification authority.
     *
     * @throws ValidationException
     */
    public static function assertValidProductClassificationCategory(
        Category $category,
        CommerceChannelCode $channelCode,
        ?string $storeId = null,
    ): void {
        self::assertCategoryMatchesChannel($category, $channelCode, $storeId);
        CatalogLeafCategoryRules::assertValidLeafParent((string) $category->id, 'category_id');
    }

    /**
     * @throws ValidationException
     */
    public static function assertCategoryMatchesChannel(
        Category $category,
        CommerceChannelCode $channelCode,
        ?string $storeId = null,
    ): void {
        $category->loadMissing(['parent', 'store']);
        $origin = $category->resolvedOrigin();

        if ($channelCode === CommerceChannelCode::ChinaImport) {
            if ($origin !== CatalogOrigin::China) {
                throw ValidationException::withMessages([
                    'category_id' => ['China import products must use China catalog categories.'],
                ]);
            }

            if (filled($category->store_id)) {
                throw ValidationException::withMessages([
                    'category_id' => ['Store-owned categories cannot be used for China import products.'],
                ]);
            }

            if (blank($category->department_id)) {
                throw ValidationException::withMessages([
                    'category_id' => ['China import products require a department-backed category.'],
                ]);
            }

            return;
        }

        if ($origin !== CatalogOrigin::Tz) {
            throw ValidationException::withMessages([
                'category_id' => ['Buy From Tanzania products must use store catalog categories.'],
            ]);
        }

        if (blank($category->store_id)) {
            throw ValidationException::withMessages([
                'category_id' => ['Selected category is not linked to a store.'],
            ]);
        }

        if (blank($storeId)) {
            throw ValidationException::withMessages([
                'store_id' => ['Buy From Tanzania products must belong to a store.'],
            ]);
        }

        if ((string) $category->store_id !== (string) $storeId) {
            throw ValidationException::withMessages([
                'category_id' => ['Category must belong to the selected store.'],
            ]);
        }
    }
}
