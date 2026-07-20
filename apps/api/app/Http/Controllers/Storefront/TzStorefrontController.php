<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerCategoryResource;
use App\Http\Resources\CustomerProductCardResource;
use App\Http\Resources\CustomerProductDetailResource;
use App\Http\Resources\StoreResource;
use App\Services\Storefront\TzStorefrontCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TzStorefrontController extends Controller
{
    public function __construct(
        private readonly TzStorefrontCatalog $catalog,
    ) {}

    public function stores(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => StoreResource::collection($this->catalog->stores()),
        ]);
    }

    public function showStore(string $store): JsonResponse
    {
        $model = $this->catalog->findStore($store);
        $model->load([
            'categories' => fn ($q) => $q->where('is_active', true)->whereNull('parent_id')
                ->with(['children' => fn ($c) => $c->where('is_active', true)->orderBy('sort_order')])
                ->orderBy('sort_order'),
        ]);

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
