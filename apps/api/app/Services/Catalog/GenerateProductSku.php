<?php

namespace App\Services\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class GenerateProductSku
{
    /**
     * Generate a unique product base SKU from catalog context.
     */
    public function handle(?Category $category = null, ?string $preferredPrefix = null): string
    {
        $originCode = $this->originCode($category?->origin);
        $categoryCode = $this->categoryCode($category);
        $prefix = strtoupper(Str::slug($preferredPrefix ?: "COT-{$originCode}-{$categoryCode}", '-'));
        $prefix = $prefix !== '' ? $prefix : 'COT-PROD';

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $candidate = sprintf('%s-%s', $prefix, strtoupper(Str::random(6)));

            if (! Product::query()->where('sku', $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf('%s-%s', $prefix, strtoupper((string) Str::uuid()));
    }

    private function originCode(CatalogOrigin|string|null $origin): string
    {
        $value = $origin instanceof CatalogOrigin ? $origin->value : $origin;

        return match ($value) {
            CatalogOrigin::China->value => 'CN',
            CatalogOrigin::Tz->value => 'TZ',
            default => 'XX',
        };
    }

    private function categoryCode(?Category $category): string
    {
        if ($category === null) {
            return 'GEN';
        }

        $slug = Str::upper(Str::slug($category->slug ?: $category->name, ''));
        $slug = preg_replace('/[^A-Z0-9]/', '', $slug) ?? '';

        if ($slug === '') {
            return 'GEN';
        }

        return substr($slug, 0, 8);
    }
}
