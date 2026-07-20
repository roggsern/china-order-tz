<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProductTypes\GenerateProductTypeConfigurationsAction;
use App\Actions\AdminProductTypes\ListProductTypesAction;
use App\Actions\AdminProductTypes\LoadCategoryProductFormSchemaAction;
use App\Actions\AdminProductTypes\ShowProductTypeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateConfigurationsRequest;
use App\Http\Resources\ProductFormSchemaResource;
use App\Http\Resources\ProductTypeResource;
use App\Models\Category;
use App\Models\ProductType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminProductTypeController extends Controller
{
    public function index(ListProductTypesAction $action): AnonymousResourceCollection
    {
        return ProductTypeResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function show(ProductType $productType, ShowProductTypeAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ProductTypeResource($action->handle($productType)),
        ]);
    }

    public function formSchema(
        Category $category,
        LoadCategoryProductFormSchemaAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new ProductFormSchemaResource($action->handle($category)),
        ]);
    }

    public function generateConfigurations(
        GenerateConfigurationsRequest $request,
        ProductType $productType,
        GenerateProductTypeConfigurationsAction $action,
    ): JsonResponse {
        $validated = $request->validated();

        return response()->json([
            'success' => true,
            'data' => [
                'configurations' => $action->handle(
                    $productType,
                    $validated['selected_values'],
                    (string) ($validated['base_sku'] ?? 'SKU'),
                    isset($validated['default_price']) ? (float) $validated['default_price'] : null,
                ),
            ],
        ]);
    }
}
