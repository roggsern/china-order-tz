<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductShippingOptionRequest;
use App\Http\Requests\Admin\SyncProductShippingOptionsRequest;
use App\Http\Requests\Admin\UpdateProductShippingOptionRequest;
use App\Http\Resources\ProductShippingOptionResource;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Services\ProductShipping\ProductShippingOptionEngine;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminProductShippingOptionController extends Controller
{
    public function __construct(
        private readonly ProductShippingOptionEngine $engine,
    ) {}

    public function index(Product $product): JsonResponse
    {
        $this->authorize(AdminPermissions::CATALOG_VIEW);

        return response()->json([
            'success' => true,
            'data' => ProductShippingOptionResource::collection(
                $this->engine->listForProduct($product)
            ),
        ]);
    }

    public function store(StoreProductShippingOptionRequest $request, Product $product): JsonResponse
    {
        $option = $this->engine->create($product, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new ProductShippingOptionResource($option),
        ], 201);
    }

    public function show(Product $product, ProductShippingOption $shippingOption): JsonResponse
    {
        $this->authorize(AdminPermissions::CATALOG_VIEW);

        abort_unless($shippingOption->product_id === $product->id, 404);

        return response()->json([
            'success' => true,
            'data' => new ProductShippingOptionResource($shippingOption),
        ]);
    }

    public function update(
        UpdateProductShippingOptionRequest $request,
        Product $product,
        ProductShippingOption $shippingOption,
    ): JsonResponse {
        $option = $this->engine->update($product, $shippingOption, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new ProductShippingOptionResource($option),
        ]);
    }

    public function destroy(Product $product, ProductShippingOption $shippingOption): JsonResponse
    {
        $this->authorize(AdminPermissions::CATALOG_UPDATE);

        $this->engine->delete($product, $shippingOption);

        return response()->json([
            'success' => true,
            'message' => 'Shipping option deleted.',
        ]);
    }

    public function restore(Product $product, string $id): JsonResponse
    {
        $this->authorize(AdminPermissions::CATALOG_RESTORE);

        $option = $this->engine->restore($product, $id);

        return response()->json([
            'success' => true,
            'data' => new ProductShippingOptionResource($option),
        ]);
    }

    public function sync(SyncProductShippingOptionsRequest $request, Product $product): JsonResponse
    {
        $options = $this->engine->syncForProduct(
            $product,
            $request->validated('shipping_options') ?? [],
        );

        return response()->json([
            'success' => true,
            'data' => ProductShippingOptionResource::collection($options),
        ]);
    }
}
