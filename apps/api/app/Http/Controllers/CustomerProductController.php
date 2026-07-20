<?php

namespace App\Http\Controllers;

use App\Actions\CustomerCatalog\ListBrandsAction;
use App\Actions\CustomerCatalog\ListCategoriesAction;
use App\Actions\CustomerCatalog\ListProductsAction;
use App\Actions\CustomerCatalog\QuoteCustomerProductPriceAction;
use App\Actions\CustomerCatalog\ShowCategoryAction;
use App\Actions\CustomerCatalog\ShowProductAction;
use App\Actions\CustomerCatalog\ShowProductConfigurationAction;
use App\Http\Requests\Customer\QuoteProductRequest;
use App\Http\Requests\Customer\ShowProductConfigurationRequest;
use App\Http\Resources\CustomerBrandResource;
use App\Http\Resources\CustomerCategoryResource;
use App\Http\Resources\CustomerProductCardResource;
use App\Http\Resources\CustomerProductDetailResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerProductController extends Controller
{
    public function index(ListProductsAction $action): AnonymousResourceCollection
    {
        return CustomerProductCardResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function show(Product $product, ShowProductAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CustomerProductDetailResource($action->handle($product)),
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
