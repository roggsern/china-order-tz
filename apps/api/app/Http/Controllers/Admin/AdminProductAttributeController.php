<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProductAttributes\GetProductCatalogAttributesAction;
use App\Actions\AdminProductAttributes\SyncProductCatalogAttributesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SyncProductCatalogAttributesRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class AdminProductAttributeController extends Controller
{
    public function index(Product $product, GetProductCatalogAttributesAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $action->handle($product),
        ]);
    }

    public function sync(
        SyncProductCatalogAttributesRequest $request,
        Product $product,
        SyncProductCatalogAttributesAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request, $product),
        ]);
    }
}
