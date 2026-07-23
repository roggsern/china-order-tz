<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProductMedia\CreateProductMediaAction;
use App\Actions\AdminProductMedia\DeleteProductMediaAction;
use App\Actions\AdminProductMedia\GetProductMediaAction;
use App\Actions\AdminProductMedia\SetPrimaryProductMediaAction;
use App\Actions\AdminProductMedia\UpdateProductMediaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductMediaRequest;
use App\Http\Requests\Admin\UpdateProductMediaRequest;
use App\Http\Resources\ProductMediaResource;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminProductMediaController extends Controller
{
    public function index(Product $product, GetProductMediaAction $action): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::CATALOG_VIEW);

        return ProductMediaResource::collection($action->handle($product))
            ->additional(['success' => true]);
    }

    public function store(
        StoreProductMediaRequest $request,
        Product $product,
        CreateProductMediaAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new ProductMediaResource($action->handle($request, $product)),
        ], 201);
    }

    public function update(
        UpdateProductMediaRequest $request,
        Product $product,
        ProductMedia $media,
        UpdateProductMediaAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new ProductMediaResource($action->handle($request, $product, $media)),
        ]);
    }

    public function destroy(
        Product $product,
        ProductMedia $media,
        DeleteProductMediaAction $action,
    ): JsonResponse {
        $this->authorize(AdminPermissions::CATALOG_UPDATE);

        $action->handle($product, $media);

        return response()->json([
            'success' => true,
            'message' => 'Media deleted successfully.',
        ]);
    }

    public function setPrimary(
        Product $product,
        ProductMedia $media,
        SetPrimaryProductMediaAction $action,
    ): JsonResponse {
        $this->authorize(AdminPermissions::CATALOG_UPDATE);

        return response()->json([
            'success' => true,
            'data' => new ProductMediaResource($action->handle($product, $media)),
        ]);
    }
}
