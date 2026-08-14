<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerCategoryResource;
use App\Http\Resources\CustomerProductCardResource;
use App\Http\Resources\CustomerProductDetailResource;
use App\Http\Resources\StoreResource;
use App\Services\Storefront\StorefrontPublicResponseCache;
use App\Services\Storefront\TzStorefrontCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TzStorefrontController extends Controller
{
    public function __construct(
        private readonly TzStorefrontCatalog $catalog,
        private readonly StorefrontPublicResponseCache $publicCache,
    ) {}

    public function stores(): JsonResponse
    {
        $data = $this->publicCache->remember(
            'tz-stores',
            'visible',
            fn () => StoreResource::collection($this->catalog->stores())->resolve(),
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function showStore(string $store): JsonResponse
    {
        $model = $this->catalog->findStore($store);
        // Same product-aware navigable roots as GET .../categories (mega-menu / store chrome).
        $model->setRelation('categories', $this->catalog->categories($model));

        return response()->json([
            'success' => true,
            'data' => new StoreResource($model),
        ]);
    }

    public function categories(string $store): AnonymousResourceCollection
    {
        $model = $this->catalog->findStore($store);

        return CustomerCategoryResource::collection($this->catalog->categories($model))
            ->additional(['success' => true]);
    }

    public function showCategory(string $store, string $category): JsonResponse
    {
        $model = $this->catalog->findStore($store);
        $resolved = $this->catalog->findCategory($model, $category);
        $payload = (new CustomerCategoryResource($resolved))->resolve();
        $payload['ancestors'] = $this->catalog->categoryAncestors($model, $resolved);

        return response()->json([
            'success' => true,
            'data' => $payload,
            'store' => [
                'id' => $model->id,
                'slug' => $model->slug,
                'name' => $model->name,
            ],
        ]);
    }

    public function products(Request $request, string $store): AnonymousResourceCollection
    {
        $model = $this->catalog->findStore($store);

        return CustomerProductCardResource::collection(
            $this->catalog->products($model, $request->only(['category', 'search', 'per_page', 'page']))
        )->additional([
            'success' => true,
            'store' => [
                'id' => $model->id,
                'slug' => $model->slug,
                'name' => $model->name,
            ],
        ]);
    }

    public function showProduct(string $store, string $product): JsonResponse
    {
        $model = $this->catalog->findStore($store);
        $item = $this->catalog->product($model, $product);

        return response()->json([
            'success' => true,
            'data' => new CustomerProductDetailResource($item),
            'store' => [
                'id' => $model->id,
                'slug' => $model->slug,
                'name' => $model->name,
            ],
        ]);
    }
}
