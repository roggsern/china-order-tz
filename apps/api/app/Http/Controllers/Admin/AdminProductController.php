<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProducts\CreateProductAction;
use App\Actions\AdminProducts\DeleteProductAction;
use App\Actions\AdminProducts\ForceDeleteProductAction;
use App\Actions\AdminProducts\GetAdminProductsAction;
use App\Actions\AdminProducts\GetProductImagesAction;
use App\Actions\AdminProducts\GetTrashedProductsAction;
use App\Actions\AdminProducts\RestoreProductAction;
use App\Actions\AdminProducts\ShowProductAction;
use App\Actions\AdminProducts\UpdateProductAction;
use App\Actions\AdminProducts\UpdateProductStockAction;
use App\Actions\AdminProducts\UploadProductImageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductImageRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Requests\Admin\UpdateProductStockRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class AdminProductController extends Controller
{
    public function index(GetAdminProductsAction $action): AnonymousResourceCollection
    {
        return ProductResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function trash(GetTrashedProductsAction $action): AnonymousResourceCollection
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

    public function updateStock(
        UpdateProductStockRequest $request,
        Product $product,
        UpdateProductStockAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => 'Stock updated successfully',
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

    public function restore(string $id, RestoreProductAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Product restored successfully.',
            'data' => new ProductResource($action->handle($id)),
        ]);
    }

    public function forceDestroy(string $id, ForceDeleteProductAction $action): JsonResponse
    {
        $action->handle($id);

        return response()->json([
            'success' => true,
            'message' => 'Product permanently deleted successfully.',
        ]);
    }

    public function storeImage(
        StoreProductImageRequest $request,
        Product $product,
        UploadProductImageAction $action,
    ): JsonResponse {
        $image = $action->handle($request, $product);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $image->id,
                'path' => $image->path,
                'url' => Storage::disk('public')->url($image->path),
            ],
        ], 201);
    }

    public function indexImages(Product $product, GetProductImagesAction $action): JsonResponse
    {
        $data = $action->handle($product)->map(fn ($image) => [
            'id' => $image->id,
            'path' => $image->path,
            'url' => Storage::disk('public')->url($image->path),
            'is_primary' => $image->is_primary,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
