<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChinaMegaMenuBrandResource;
use App\Http\Resources\ChinaMegaMenuProductResource;
use App\Http\Resources\CustomerBrandResource;
use App\Http\Resources\CustomerCategoryResource;
use App\Http\Resources\CustomerProductCardResource;
use App\Services\Storefront\ChinaStorefrontCatalog;
use App\Services\Storefront\ChinaStorefrontMenuCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChinaStorefrontController extends Controller
{
    public function __construct(
        private readonly ChinaStorefrontCatalog $catalog,
        private readonly ChinaStorefrontMenuCache $menuCache,
    ) {}

    public function categories(): AnonymousResourceCollection
    {
        return CustomerCategoryResource::collection($this->catalog->navigationCategories())
            ->additional(['success' => true]);
    }

    public function featuredCollections(): AnonymousResourceCollection
    {
        return CustomerCategoryResource::collection($this->catalog->featuredCollectionCategories())
            ->additional(['success' => true]);
    }

    public function brands(Request $request): AnonymousResourceCollection
    {
        $category = $request->query('category');

        return CustomerBrandResource::collection(
            $this->catalog->brands(is_string($category) ? $category : null)
        )->additional(['success' => true]);
    }

    public function products(Request $request): AnonymousResourceCollection
    {
        return CustomerProductCardResource::collection(
            $this->catalog->products($request->only(['category', 'brand', 'featured', 'search', 'per_page', 'page']))
        )->additional(['success' => true]);
    }

    public function menu(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $categorySlug = is_string($category) && trim($category) !== ''
            ? trim($category)
            : null;

        $data = $this->menuCache->remember(
            $categorySlug,
            fn () => $this->buildMenuPayload($categorySlug),
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * @return array{
     *     label: string,
     *     categories: list<array<string, mixed>>,
     *     active_category: string|null,
     *     brands: list<array<string, mixed>>,
     *     featured_products: list<array<string, mixed>>
     * }
     */
    private function buildMenuPayload(?string $categorySlug): array
    {
        $categories = $this->catalog->navigationCategories();
        $activeSlug = $categorySlug ?? $categories->first()?->slug;

        $brands = $activeSlug
            ? $this->catalog->menuBrands($activeSlug, 12)
            : collect();

        $featured = $activeSlug
            ? $this->catalog->menuProducts([
                'category' => $activeSlug,
                'featured' => true,
                'per_page' => 6,
            ])
            : collect();

        if ($featured->isEmpty() && $activeSlug) {
            $featured = $this->catalog->menuProducts([
                'category' => $activeSlug,
                'per_page' => 6,
            ]);
        }

        return [
            'label' => 'ORDER FROM CHINA',
            'categories' => CustomerCategoryResource::collection($categories)->resolve(),
            'active_category' => $activeSlug,
            'brands' => ChinaMegaMenuBrandResource::collection($brands)->resolve(),
            'featured_products' => ChinaMegaMenuProductResource::collection($featured)->resolve(),
        ];
    }
}
