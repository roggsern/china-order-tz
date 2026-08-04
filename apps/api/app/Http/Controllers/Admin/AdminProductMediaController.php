<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProductMedia\ApplyAttributeOptionMediaAction;
use App\Actions\AdminProductMedia\CreateProductMediaAction;
use App\Actions\AdminProductMedia\DeleteProductMediaAction;
use App\Actions\AdminProductMedia\GetProductMediaAction;
use App\Actions\AdminProductMedia\SetPrimaryProductMediaAction;
use App\Actions\AdminProductMedia\UpdateProductMediaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplyAttributeOptionMediaRequest;
use App\Http\Requests\Admin\IndexProductMediaRequest;
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
    public function index(
        IndexProductMediaRequest $request,
        Product $product,
        GetProductMediaAction $action,
    ): AnonymousResourceCollection {
        $variantId = $request->validated('product_variant_id');

        return ProductMediaResource::collection(
            $action->handle($product, is_string($variantId) ? $variantId : null),
        )->additional(['success' => true]);
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

    public function applyToAttributeOption(
        ApplyAttributeOptionMediaRequest $request,
        Product $product,
        ApplyAttributeOptionMediaAction $action,
    ): JsonResponse {
        $result = $action->handle($request, $product);

        return response()->json([
            'success' => true,
            'data' => [
                'catalog_attribute_option_id' => $result['catalog_attribute_option_id'],
                'option_value' => $result['option_value'],
                'attribute_name' => $result['attribute_name'],
                'url' => $result['url'],
                'matched_variant_count' => $result['matched_variant_count'],
                'applied_count' => $result['applied_count'],
                'skipped_count' => $result['skipped_count'],
                'skipped_variant_ids' => $result['skipped_variant_ids'],
                'media' => $result['media']
                    ->map(fn ($media) => (new ProductMediaResource($media))->resolve())
                    ->values()
                    ->all(),
            ],
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
