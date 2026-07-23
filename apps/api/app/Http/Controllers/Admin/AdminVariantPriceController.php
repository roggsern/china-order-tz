<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminVariantPrices\CreateVariantPriceAction;
use App\Actions\AdminVariantPrices\DeleteVariantPriceAction;
use App\Actions\AdminVariantPrices\GetVariantPricesAction;
use App\Actions\AdminVariantPrices\UpdateVariantPriceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVariantPriceRequest;
use App\Http\Requests\Admin\UpdateVariantPriceRequest;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminVariantPriceController extends Controller
{
    public function index(ProductVariant $variant, GetVariantPricesAction $action): JsonResponse
    {
        $this->authorize(AdminPermissions::PRICING_VIEW);

        return response()->json([
            'success' => true,
            'data' => $action->handle($variant),
        ]);
    }

    public function store(
        StoreVariantPriceRequest $request,
        ProductVariant $variant,
        CreateVariantPriceAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request, $variant),
        ], 201);
    }

    public function update(
        UpdateVariantPriceRequest $request,
        VariantPrice $price,
        UpdateVariantPriceAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request, $price),
        ]);
    }

    public function destroy(VariantPrice $price, DeleteVariantPriceAction $action): JsonResponse
    {
        $this->authorize(AdminPermissions::PRICING_MANAGE);

        $action->handle($price);

        return response()->json([
            'success' => true,
            'message' => 'Price deleted.',
        ]);
    }
}
