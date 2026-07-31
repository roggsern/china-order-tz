<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Store;
use App\Services\Stores\StoreOperationsDashboardService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreDashboardController extends Controller
{
    public function __construct(
        private readonly StoreOperationsDashboardService $dashboard,
    ) {}

    public function show(Request $request, Store $store): JsonResponse
    {
        $this->authorize(AdminPermissions::STORES_VIEW);

        /** @var Admin $admin */
        $admin = $request->user();

        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->dashboard->dashboard($admin, $store, $validated),
        ]);
    }
}
