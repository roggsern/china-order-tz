<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminVariantInventories\CreateVariantInventoryAction;
use App\Actions\AdminVariantInventories\DeleteVariantInventoryAction;
use App\Actions\AdminVariantInventories\GetVariantInventoriesAction;
use App\Actions\AdminVariantInventories\UpdateVariantInventoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVariantInventoryRequest;
use App\Http\Requests\Admin\UpdateVariantInventoryRequest;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminVariantInventoryController extends Controller
{
    public function index(ProductVariant $variant, GetVariantInventoriesAction $action): JsonResponse
    {
        $this->authorize(AdminPermissions::INVENTORY_VIEW);

        return response()->json([
            'success' => true,
            'data' => $action->handle($variant),
        ]);
    }

    public function store(
        StoreVariantInventoryRequest $request,
        ProductVariant $variant,
        CreateVariantInventoryAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request, $variant),
        ], 201);
    }

    public function update(
        UpdateVariantInventoryRequest $request,
        VariantInventory $inventory,
        UpdateVariantInventoryAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request, $inventory),
        ]);
    }

    public function destroy(
        VariantInventory $inventory,
        DeleteVariantInventoryAction $action,
    ): JsonResponse {
        $this->authorize(AdminPermissions::INVENTORY_ADJUST);

        $action->handle($inventory);

        return response()->json([
            'success' => true,
            'message' => 'Inventory deleted.',
        ]);
    }
}
