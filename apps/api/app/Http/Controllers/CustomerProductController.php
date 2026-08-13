<?php

namespace App\Http\Controllers;

use App\Actions\CustomerCatalog\ListBrandsAction;
use App\Actions\CustomerCatalog\ListCategoriesAction;
use App\Actions\CustomerCatalog\ListProductsAction;
use App\Actions\CustomerCatalog\QuoteCustomerProductPriceAction;
use App\Actions\CustomerCatalog\ShowCategoryAction;
use App\Actions\CustomerCatalog\ShowProductAction;
use App\Actions\CustomerCatalog\ShowProductCheckoutSummaryAction;
use App\Actions\CustomerCatalog\ShowProductConfigurationAction;
use App\Http\Requests\Customer\QuoteProductRequest;
use App\Http\Requests\Customer\ShowProductConfigurationRequest;
use App\Http\Resources\CustomerBrandResource;
use App\Http\Resources\CustomerCategoryResource;
use App\Http\Resources\CustomerProductCardResource;
use App\Http\Resources\CustomerProductDetailResource;
use App\Models\Product;
use App\Services\Storefront\StorefrontPublicResponseCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerProductController extends Controller
{
    public function __construct(
        private readonly StorefrontPublicResponseCache $publicCache,
    ) {}

    public function index(Request $request, ListProductsAction $action): JsonResponse|AnonymousResourceCollection
    {
        if (! $this->publicCache->isCacheableProductList($request)) {
            return CustomerProductCardResource::collection($action->handle())
                ->additional(['success' => true]);
        }

        $payload = $this->publicCache->remember(
            'products',
            $this->publicCache->productListVariant($request),
            function () use ($action) {
                $paginator = $action->handle();

                return [
                    'success' => true,
                    'data' => CustomerProductCardResource::collection(
                        collect($paginator->items()),
                    )->resolve(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ];
            },
        );

        return response()->json($payload);
    }

    public function show(Product $product, ShowProductAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CustomerProductDetailResource($action->handle($product)),
        ]);
    }

    /**
     * Slim card-shaped product for Continue-to-Payment client validation.
     * Does not replace PDP show — keeps pricing/stock/purchasability authority.
     */
    public function checkoutSummary(
        Product $product,
        ShowProductCheckoutSummaryAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new CustomerProductCardResource($action->handle($product)),
        ]);
    }

    public function configuration(
        ShowProductConfigurationRequest $request,
        Product $product,
        ShowProductConfigurationAction $action,
    ): JsonResponse {
        $selections = $request->validated('selections') ?? [];

        return response()->json([
            'success' => true,
            'data' => $action->handle($product, $selections),
        ]);
    }

    public function quote(
        QuoteProductRequest $request,
        Product $product,
        QuoteCustomerProductPriceAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request, $product)->toArray(),
        ]);
    }

    public function categories(ListCategoriesAction $action): AnonymousResourceCollection
    {
        return CustomerCategoryResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function showCategory(string $slug, ShowCategoryAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CustomerCategoryResource($action->handle($slug)),
        ]);
    }

    public function brands(ListBrandsAction $action): AnonymousResourceCollection
    {
        return CustomerBrandResource::collection($action->handle())
            ->additional(['success' => true]);
    }
}
