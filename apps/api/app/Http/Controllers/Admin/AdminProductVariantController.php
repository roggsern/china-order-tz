<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProductVariants\CreateProductVariantAction;
use App\Actions\AdminProductVariants\DeleteProductVariantAction;
use App\Actions\AdminProductVariants\GenerateProductVariantsAction;
use App\Actions\AdminProductVariants\GetProductVariantsAction;
use App\Actions\AdminProductVariants\UpdateProductVariantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateProductVariantsRequest;
use App\Http\Requests\Admin\StoreProductVariantRequest;
use App\Http\Requests\Admin\UpdateProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;

class AdminProductVariantController extends Controller
{
    public function index(Product $product, GetProductVariantsAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $action->handle($product),
        ]);
    }

    public function store(
        StoreProductVariantRequest $request,
        Product $product,
        CreateProductVariantAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request, $product),
        ], 201);
    }

    public function update(
        UpdateProductVariantRequest $request,
        Product $product,
        ProductVariant $variant,
        UpdateProductVariantAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request, $product, $variant),
        ]);
    }

    public function destroy(
        Product $product,
        ProductVariant $variant,
        DeleteProductVariantAction $action,
    ): JsonResponse {
        $action->handle($product, $variant);

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted.',
        ]);
    }

    public function generate(
        GenerateProductVariantsRequest $request,
        Product $product,
        GenerateProductVariantsAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request, $product),
        ]);
    }
}
