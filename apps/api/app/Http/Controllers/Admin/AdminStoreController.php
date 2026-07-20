<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Models\Admin;
use App\Models\Store;
use App\Services\Stores\ActiveStoreContext;
use App\Services\Stores\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminStoreController extends Controller
{
    public function __construct(
        private readonly StoreService $stores,
        private readonly ActiveStoreContext $storeContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $stores = $admin->is_super_admin
            ? Store::query()->with(['defaultInventoryLocation', 'terminals'])->orderBy('sort_order')->orderBy('name')->get()
            : $this->storeContext->assignedStores($admin)->load(['defaultInventoryLocation', 'terminals']);

        return response()->json([
            'success' => true,
            'data' => StoreResource::collection($stores),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        abort_unless($admin->is_super_admin, 403, 'Only super admins may create stores.');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:stores,code'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:stores,slug'],
            'description' => ['nullable', 'string'],
            'logo_path' => ['nullable', 'string', 'max:500'],
            'banner_path' => ['nullable', 'string', 'max:500'],
            'theme_color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'storefront_enabled' => ['sometimes', 'boolean'],
            'storefront_visible' => ['sometimes', 'boolean'],
            'storefront_featured' => ['sometimes', 'boolean'],
            'storefront_sort_order' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'settings' => ['nullable', 'array'],
        ]);

        $store = $this->stores->create($data, $admin);

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
        ], 201);
    }

    public function show(Request $request, Store $store): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->storeContext->assertCanAccess($admin, $store);

        $store->load(['defaultInventoryLocation', 'inventoryLocations', 'terminals']);

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
        ]);
    }

    public function update(Request $request, Store $store): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        abort_unless($admin->is_super_admin, 403, 'Only super admins may update stores.');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('stores', 'slug')->ignore($store->id)],
            'description' => ['nullable', 'string'],
            'logo_path' => ['nullable', 'string', 'max:500'],
            'banner_path' => ['nullable', 'string', 'max:500'],
            'theme_color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'storefront_enabled' => ['sometimes', 'boolean'],
            'storefront_visible' => ['sometimes', 'boolean'],
            'storefront_featured' => ['sometimes', 'boolean'],
            'storefront_sort_order' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'settings' => ['nullable', 'array'],
        ]);

        $store = $this->stores->update($store, $data, $admin);

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
        ]);
    }
}
