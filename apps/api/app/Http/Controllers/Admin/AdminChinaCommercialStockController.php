<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminChinaCommercialStock\GetProductCommercialStockAction;
use App\Actions\AdminChinaCommercialStock\UpdateProductCommercialStockAction;
use App\Actions\AdminChinaCommercialStock\UpdateVariantCommercialStockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProductCommercialStockRequest;
use App\Http\Requests\Admin\UpdateVariantCommercialStockRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminChinaCommercialStockController extends Controller
{
    public function show(Product $product, GetProductCommercialStockAction $action): JsonResponse
    {
        $this->authorize(AdminPermissions::INVENTORY_VIEW);

        return response()->json([
            'success' => true,
            'data' => $action->handle($product),
        ]);
    }

    public function updateProduct(
        UpdateProductCommercialStockRequest $request,
        Product $product,
        UpdateProductCommercialStockAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => 'Commercial availability updated.',
            'data' => $action->handle($request, $product),
        ]);
    }

    public function updateVariant(
        UpdateVariantCommercialStockRequest $request,
        ProductVariant $variant,
        UpdateVariantCommercialStockAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => 'Variant commercial availability updated.',
            'data' => $action->handle($request, $variant),
        ]);
    }
}
