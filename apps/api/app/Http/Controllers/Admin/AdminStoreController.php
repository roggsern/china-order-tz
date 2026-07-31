<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStoreRequest;
use App\Http\Requests\Admin\UpdateStoreRequest;
use App\Http\Requests\Admin\UpdateStoreStatusRequest;
use App\Http\Requests\Admin\UploadStoreBrandingRequest;
use App\Http\Resources\StoreResource;
use App\Models\Admin;
use App\Models\Store;
use App\Services\Stores\ActiveStoreContext;
use App\Services\Stores\StoreBrandingMediaService;
use App\Services\Stores\StoreService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreController extends Controller
{
    public function __construct(
        private readonly StoreService $stores,
        private readonly ActiveStoreContext $storeContext,
        private readonly StoreBrandingMediaService $branding,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::STORES_VIEW);

        /** @var Admin $admin */
        $admin = $request->user();

        $stores = $this->storeContext->manageableStores($admin)
            ->load(['defaultInventoryLocation', 'terminals']);

        return response()->json([
            'success' => true,
            'data' => StoreResource::collection($stores),
        ]);
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $store = $this->stores->create($request->validated(), $admin);

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
        ], 201);
    }

    public function show(Request $request, Store $store): JsonResponse
    {
        $this->authorize(AdminPermissions::STORES_VIEW);

        /** @var Admin $admin */
        $admin = $request->user();
        $this->storeContext->assertCanView($admin, $store);

        $store->load(['defaultInventoryLocation', 'inventoryLocations', 'terminals']);

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
        ]);
    }

    public function update(UpdateStoreRequest $request, Store $store): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->storeContext->assertCanManage($admin, $store);

        $store = $this->stores->update($store, $request->validated(), $admin);

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
        ]);
    }

    public function updateStatus(UpdateStoreStatusRequest $request, Store $store): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->storeContext->assertCanManage($admin, $store);

        $store = $this->stores->updateStatus(
            $store,
            (bool) $request->validated('is_active'),
            $admin,
        );

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
        ]);
    }

    public function uploadBranding(UploadStoreBrandingRequest $request, Store $store): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->storeContext->assertCanManage($admin, $store);

        $store = $this->branding->upload($store, [
            'logo' => $request->file('logo'),
            'banner' => $request->file('banner'),
        ], $admin);

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store->load(['defaultInventoryLocation', 'terminals'])),
        ]);
    }
}
