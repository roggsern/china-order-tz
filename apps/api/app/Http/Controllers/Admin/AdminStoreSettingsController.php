<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStoreSettingsRequest;
use App\Models\Store;
use App\Services\Stores\StoreSettingsService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminStoreSettingsController extends Controller
{
    public function __construct(
        private readonly StoreSettingsService $storeSettings,
    ) {}

    public function show(Store $store): JsonResponse
    {
        $this->authorize(AdminPermissions::STORES_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->storeSettings->getSettings($store, request()->user()),
        ]);
    }

    public function update(UpdateStoreSettingsRequest $request, Store $store): JsonResponse
    {
        $settings = $this->storeSettings->updateSettings(
            $store,
            $request->all(),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Store settings updated successfully.',
            'data' => $settings,
        ]);
    }
}
