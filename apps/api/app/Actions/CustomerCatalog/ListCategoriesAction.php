<?php

namespace App\Actions\CustomerCatalog;

use App\Enums\CatalogOrigin;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Category;
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

        // ORDER FROM CHINA navigation: only categories with published China-import products.
        if (request()->boolean('china_navigation') || request()->boolean('navigation')) {
            $query->where(function (Builder $q) {
                $q->whereHas('products', fn (Builder $p) => $this->chinaPublished($p))
                    ->orWhereHas('children', function (Builder $child) {
                        $child->where('is_active', true)
                            ->whereNull('store_id')
                            ->where(function (Builder $inner) {
                                $inner->whereHas('products', fn (Builder $p) => $this->chinaPublished($p))
                                    ->orWhereHas('children.products', fn (Builder $p) => $this->chinaPublished($p));
                            });
                    });
            });
        }
    }

    private function isChinaOriginRequest(): bool
    {
        $origin = request()->query('origin');

        return is_string($origin) && strtolower($origin) === CatalogOrigin::China->value;
    }

    private function chinaPublished(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_demo', false)
            ->where('lifecycle_status', ProductLifecycleStatus::Active)
            ->where('visibility', ProductVisibility::Public)
            ->whereNull('store_id')
            ->whereHas('commerceChannel', fn (Builder $q) => $q->where('code', CommerceChannelCode::ChinaImport->value));
    }
}
