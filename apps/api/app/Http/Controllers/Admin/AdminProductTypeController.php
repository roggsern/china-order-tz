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
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin Configuration Template APIs (ADR 052).
 *
 * Routes under /admin/product-types expose the legacy ProductType model
 * (configuration schema). Catalog taxonomy CRUD lives under
 * /admin/catalog-product-types and the Admin "Product Types" screen.
 */
class AdminProductTypeController extends Controller
{
    public function index(ListProductTypesAction $action): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::CONFIGURATION_VIEW);

        return ProductTypeResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function show(ProductType $productType, ShowProductTypeAction $action): JsonResponse
    {
        $this->authorize(AdminPermissions::CONFIGURATION_VIEW);

        return response()->json([
            'success' => true,
            'data' => new ProductTypeResource($action->handle($productType)),
        ]);
    }

    public function formSchema(
        Category $category,
        LoadCategoryProductFormSchemaAction $action,
    ): JsonResponse {
        $this->authorize(AdminPermissions::CONFIGURATION_VIEW);

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
