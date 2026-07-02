<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProducts\CreateProductAction;
use App\Actions\AdminProducts\DeleteProductAction;
use App\Actions\AdminProducts\GetAdminProductsAction;
use App\Actions\AdminProducts\ShowProductAction;
use App\Actions\AdminProducts\UpdateProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminProductController extends Controller
{
    public function index(GetAdminProductsAction $action): AnonymousResourceCollection
    {
        return ProductResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function store(StoreProductRequest $request, CreateProductAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ProductResource($action->handle($request)),
        ], 201);
    }

    public function show(Product $product, ShowProductAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ProductResource($action->handle($product)),
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        UpdateProductAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new ProductResource($action->handle($request, $product)),
        ]);
    }

    public function destroy(Product $product, DeleteProductAction $action): JsonResponse
    {
        $action->handle($product);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }
}
