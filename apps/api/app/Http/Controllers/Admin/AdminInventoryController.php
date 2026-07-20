<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryCountSessionResource;
use App\Http\Resources\InventoryStockMovementResource;
use App\Http\Resources\ReceivingRecordResource;
use App\Http\Resources\VariantInventoryResource;
use App\Models\Admin;
use App\Models\InventoryCountSession;
use App\Models\InventoryStockMovement;
use App\Models\ReceivingRecord;
use App\Models\VariantInventory;
use App\Services\Inventory\InventoryControlEngine;
use App\Services\Stores\ActiveStoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AdminInventoryController extends Controller
{
    public function __construct(
        private readonly InventoryControlEngine $inventory,
        private readonly ActiveStoreContext $stores,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $storeIds = $this->scopedStoreIds($admin, $request->query('store_id'));

        return response()->json([
            'success' => true,
            'data' => $this->inventory->dashboard($storeIds),
        ]);
    }

    public function stockLevels(Request $request): AnonymousResourceCollection
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $storeIds = $this->scopedStoreIds($admin, $request->query('store_id'));
        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);

        $query = VariantInventory::query()
            ->where('is_active', true)
            ->with(['variant.product', 'inventoryLocation.store'])
            ->latest('updated_at');

        if ($storeIds !== []) {
            $query->whereHas('inventoryLocation', fn ($q) => $q->whereIn('store_id', $storeIds));
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->whereHas('variant', function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        return VariantInventoryResource::collection($query->paginate($perPage));
    }

    public function movements(Request $request): AnonymousResourceCollection
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $storeIds = $this->scopedStoreIds($admin, $request->query('store_id'));
        $perPage = min(max((int) $request->query('per_page', 40), 1), 100);

        $query = InventoryStockMovement::query()
            ->with(['variant.product', 'store:id,code,name'])
            ->latest('created_at');

        if ($storeIds !== []) {
            $query->whereIn('store_id', $storeIds);
        }

        if ($type = $request->query('movement_type')) {
            $query->where('movement_type', $type);
        }

        return InventoryStockMovementResource::collection($query->paginate($perPage));
    }

    public function receiving(Request $request): AnonymousResourceCollection
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $storeIds = $this->scopedStoreIds($admin, $request->query('store_id'));
        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);

        $query = ReceivingRecord::query()
            ->with(['purchaseOrder.supplier', 'receivedByAdmin:id,name', 'store:id,code,name', 'items'])
            ->latest('received_at');

        if ($storeIds !== []) {
            $query->whereIn('store_id', $storeIds);
        }

        return ReceivingRecordResource::collection($query->paginate($perPage));
    }

    public function adjust(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $data = $request->validate([
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'product_variant_id' => ['required', 'uuid', 'exists:product_variants,id'],
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
            'kind' => ['sometimes', 'string', Rule::in(['adjustment', 'correction', 'damage', 'found'])],
        ]);

        $store = $this->stores->resolveActiveStore($admin, $data['store_id']);
        $movement = $this->inventory->adjust(
            $store,
            $data['product_variant_id'],
            (int) $data['quantity_change'],
            $data['reason'],
            $admin,
            $data['kind'] ?? 'adjustment',
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock adjusted.',
            'data' => new InventoryStockMovementResource($movement->load(['variant.product', 'store'])),
        ], 201);
    }

    public function createCount(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $data = $request->validate([
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'scope' => ['sometimes', 'string', Rule::in(['full', 'category', 'selected'])],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'variant_ids' => ['nullable', 'array'],
            'variant_ids.*' => ['uuid', 'exists:product_variants,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $store = $this->stores->resolveActiveStore($admin, $data['store_id']);
        $session = $this->inventory->createCountSession($store, $data, $admin);

        return response()->json([
            'success' => true,
            'data' => new InventoryCountSessionResource($session),
        ], 201);
    }

    public function showCount(InventoryCountSession $count): JsonResponse
    {
        /** @var Admin $admin */
        $admin = request()->user();
        $this->stores->assertCanAccess($admin, $count->store);

        return response()->json([
            'success' => true,
            'data' => new InventoryCountSessionResource(
                $count->load(['lines.variant.product', 'store', 'location'])
            ),
        ]);
    }

    public function recordCount(InventoryCountSession $count, Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->stores->assertCanAccess($admin, $count->store);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'uuid'],
            'lines.*.counted_quantity' => ['required', 'integer', 'min:0'],
            'lines.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        $session = $this->inventory->recordCountLines($count, $data['lines']);

        return response()->json([
            'success' => true,
            'data' => new InventoryCountSessionResource($session),
        ]);
    }

    public function submitCount(InventoryCountSession $count): JsonResponse
    {
        /** @var Admin $admin */
        $admin = request()->user();
        $this->stores->assertCanAccess($admin, $count->store);

        return response()->json([
            'success' => true,
            'data' => new InventoryCountSessionResource($this->inventory->submitCount($count)),
        ]);
    }

    public function approveCount(InventoryCountSession $count, Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->stores->assertCanAccess($admin, $count->store);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $session = $this->inventory->approveCount($count, $admin, $data['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Stock count approved and variances applied.',
            'data' => new InventoryCountSessionResource($session),
        ]);
    }

    public function counts(Request $request): AnonymousResourceCollection
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $storeIds = $this->scopedStoreIds($admin, $request->query('store_id'));

        $query = InventoryCountSession::query()->with('store:id,code,name')->latest();
        if ($storeIds !== []) {
            $query->whereIn('store_id', $storeIds);
        }

        return InventoryCountSessionResource::collection($query->paginate(20));
    }

    public function valuation(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $storeIds = $this->scopedStoreIds($admin, $request->query('store_id'));

        return response()->json([
            'success' => true,
            'data' => $this->inventory->valuation($storeIds),
        ]);
    }

    public function lowStock(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $storeIds = $this->scopedStoreIds($admin, $request->query('store_id'));

        return response()->json([
            'success' => true,
            'data' => $this->inventory->lowStock($storeIds),
        ]);
    }

    /**
     * @return list<string>
     */
    private function scopedStoreIds(Admin $admin, ?string $storeId): array
    {
        if ($storeId) {
            $store = $this->stores->resolveActiveStore($admin, $storeId);

            return [$store->id];
        }

        return $this->stores->assignedStores($admin)->pluck('id')->all();
    }
}
