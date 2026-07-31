<?php

namespace App\Actions\CustomerCatalog;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use App\Services\Storefront\ChinaStorefrontCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListCategoriesAction
{
    /**
     * Navigation taxonomy from the database (source of truth).
     * Includes empty branches so the catalog engine can render without inventing nodes.
     *
     * @return Collection<int, Category>
     */
    public function handle(): Collection
    {
        if ($this->usesCrosswalkNavigation()) {
            return app(ChinaStorefrontCatalog::class)->navigationCategories();
        }

        $query = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        $this->applyFilters($query);

        $tree = request()->boolean('tree', true);
        $chinaScoped = $this->isChinaOriginRequest();

        if ($tree && ! request()->query->has('parent_id')) {
            $with = $chinaScoped
                ? [
                    'children' => fn ($q) => $q->where('is_active', true)
                        ->whereNull('store_id')
                        ->where('origin', CatalogOrigin::China)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->with([
                            'children' => fn ($c) => $c->where('is_active', true)
                                ->whereNull('store_id')
                                ->where('origin', CatalogOrigin::China)
                                ->orderBy('sort_order')
                                ->orderBy('name'),
                        ]),
                ]
                : ['childrenRecursive'];

            return $query
                ->whereNull('parent_id')
                ->with($with)
                ->get();
        }

        return $query->get(['id', 'parent_id', 'origin', 'name', 'slug', 'sort_order', 'store_id']);
    }

    private function applyFilters(Builder $query): void
    {
        $origin = request()->query('origin');
        if (is_string($origin) && $origin !== '') {
            $query->where('origin', $origin);
        }

        // China catalog never includes BUY FROM TZ store categories.
        if ($this->isChinaOriginRequest()) {
            $query->whereNull('store_id');
        }

        if (request()->query->has('parent_id')) {
            $parentId = request()->query('parent_id');
            if ($parentId === null || $parentId === '' || $parentId === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $parentId);
            }
        }

        $store = request()->query('store');
        if (is_string($store) && $store !== '') {
            $query->where(function (Builder $q) use ($store) {
                $q->where('store_id', $store)
                    ->orWhereHas('store', fn (Builder $sq) => $sq->where('slug', $store));
            });
        }

        // Legacy: only categories that currently have active products.
        if (request()->boolean('with_products')) {
            $query->whereHas('products', fn (Builder $q) => $q->active());
        }
    }

    private function usesCrosswalkNavigation(): bool
    {
        return request()->boolean('china_navigation') || request()->boolean('navigation');
    }

    private function isChinaOriginRequest(): bool
    {
        $origin = request()->query('origin');

        return is_string($origin) && strtolower($origin) === CatalogOrigin::China->value;
    }
}
