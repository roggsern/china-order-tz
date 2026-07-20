<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerBrandResource;
use App\Http\Resources\CustomerCategoryResource;
use App\Http\Resources\CustomerProductCardResource;
use App\Services\Storefront\ChinaStorefrontCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChinaStorefrontController extends Controller
{
    public function __construct(
        private readonly ChinaStorefrontCatalog $catalog,
    ) {}

    public function categories(): AnonymousResourceCollection
    {
        return CustomerCategoryResource::collection($this->catalog->navigationCategories())
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
            $this->catalog->products($request->only(['category', 'brand', 'featured', 'per_page', 'page']))
        )->additional(['success' => true]);
    }

    public function menu(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $categories = $this->catalog->navigationCategories();
        $activeSlug = is_string($category) && $category !== ''
            ? $category
            : ($categories->first()?->slug);

        $brands = $activeSlug
            ? $this->catalog->brands($activeSlug, 12)
            : collect();

        $featured = $this->catalog->products([
            'category' => $activeSlug,
            'featured' => true,
            'per_page' => 6,
        ]);

        if ($featured->total() === 0 && $activeSlug) {
            $featured = $this->catalog->products([
                'category' => $activeSlug,
                'per_page' => 6,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'label' => 'ORDER FROM CHINA',
                'categories' => CustomerCategoryResource::collection($categories),
                'active_category' => $activeSlug,
                'brands' => CustomerBrandResource::collection($brands),
                'featured_products' => CustomerProductCardResource::collection($featured->items()),
            ],
        ]);
    }
}
